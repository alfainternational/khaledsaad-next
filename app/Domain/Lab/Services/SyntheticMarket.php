<?php

namespace App\Domain\Lab\Services;

use App\Domain\Assessment\Services\Catalog;
use App\Domain\Lab\Ai\AiContext;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolVersion;
use DomainException;
use Illuminate\Support\Str;

/**
 * السوق المحاكى (spec §4 item 1 "synthetic-market agents", plan 2026-11-09 البند 3): the member's message is read by a
 * small panel of model-played customer voices, each answering alone, and the verdict is assembled **in PHP** — not by a
 * summarising model call. A generated summary would be one more thing to trust; counting is not.
 *
 * Three locks keep a multi-call feature bounded (the cost question the blueprint audit raised):
 *   1. `plan()` clamps voices and rounds to config('ai.panel') BEFORE anything is spent, and prices the run from the
 *      clamped numbers — so the ceiling is arithmetic, not a sentence in a prompt.
 *   2. Each voice gets its own small output budget (AiContext::$maxOutputTokens), which can only lower the platform cap.
 *   3. The monthly workspace allowance from البند 1 still applies to every single call.
 *
 * Reproducibility, not determinism: a language model is not deterministic at any temperature, so what this guarantees is
 * that the RECORD can be reproduced — seed, instructions, provider, model and version are stored, and identical input
 * returns the cached answer for a day (`ai.panel.cache_ttl_minutes`), which is what an audit and a fair A/B both need.
 *
 * The output carries «محاكاة لا بحث سوق» inside the Markdown itself, not only in the page, because an asset gets copied
 * out of the page and a platform that sells editorial trust cannot let generated voices read as field data.
 */
class SyntheticMarket
{
    public const VERDICTS = ['buy' => 'سيشتري', 'maybe' => 'مهتم بشرط', 'no' => 'لن يشتري', 'unclear' => 'لم يتضح'];

    /** The role a voice plays. Kept here (not in the prompt file) so the tool studio can override it per version. */
    public const INSTRUCTIONS = <<<'TXT'
        أنت تلعب دور عميل محتمل واحد في لوحة محاكاة، لا دور خبير تسويق ولا دور الشركة. تحكم على الرسالة المعروضة عليك وحدها.
        أجب بـ JSON فقط، بهذه المفاتيح الأربعة ولا شيء غيرها:
        {"verdict":"buy|maybe|no","reason":"...","objection":"...","unlock":"..."}
        - verdict: قرارك الآن. buy = ستشتري، maybe = مهتم بشرط واضح، no = لن تشتري.
        - reason: سببك بلغتك أنت، جملة أو جملتان، بلا مصطلحات تسويقية.
        - objection: أقوى ما يمنعك، جملة واحدة. اتركها "" إن لم يوجد مانع.
        - unlock: شيء واحد محدد يجعلك تقرر الشراء فعلاً.
        لا تخترع أرقاماً ولا إحصاءات ولا أسماء منتجات لم تُذكر، ولا تكتب أي نص خارج JSON.
        TXT;

    public function __construct(private readonly AiGateway $ai, private readonly GeneratorEngine $engine) {}

    /** The clamped ceiling of one run, as config declares it. */
    public static function caps(): array
    {
        return [
            'voices' => max(1, (int) config('ai.panel.max_voices', 5)),
            'rounds' => max(1, (int) config('ai.panel.max_rounds', 2)),
            'output_tokens' => max(1, (int) config('ai.panel.max_output_tokens', 600)),
            'cache_minutes' => max(0, (int) config('ai.panel.cache_ttl_minutes', 1440)),
        ];
    }

    /**
     * Pure and free: normalise the input, clamp it to the caps and price the run. Nothing here calls a provider or
     * touches the ledger, so the controller can refuse an unaffordable run before a single credit is reserved.
     *
     * @return array{input: array, missing: array, voices: array<int, string>, rounds: int, calls: int, credits: int, seed: int, clamped: array<int, string>}
     */
    public function plan(Tool $tool, array $raw): array
    {
        $caps = self::caps();
        $fields = $tool->fields();
        $input = $this->engine->normalise($fields, $raw);
        $missing = $this->engine->missing($fields, $input);

        $lines = $this->lines((string) ($input['personas'] ?? ''));
        $voices = array_slice($lines, 0, $caps['voices']);
        $rounds = min($caps['rounds'], max(1, (int) ($input['rounds'] ?? 1)));

        $clamped = [];
        if (count($lines) > $caps['voices']) {
            $clamped[] = 'اللوحة محدودة بـ'.$caps['voices'].' شخصيات؛ أُخذت الأولى وتُركت '.(count($lines) - $caps['voices']).'.';
        }
        if ((int) ($input['rounds'] ?? 1) > $caps['rounds']) {
            $clamped[] = 'الجولات محدودة بـ'.$caps['rounds'].'.';
        }
        if ($voices === [] && ! isset($missing['personas'])) {
            $missing['personas'] = 'الشخصيات (سطر لكل شخصية)';
        }

        // The input itself is the seed: the same message and the same panel reproduce the same record.
        $seed = (int) sprintf('%u', crc32(json_encode([$input['offer'] ?? '', $input['message'] ?? '', $voices, $rounds], JSON_UNESCAPED_UNICODE) ?: ''));
        $calls = count($voices) * $rounds;

        return [
            'input' => ['personas' => implode("\n", $voices), 'rounds' => $rounds] + $input,
            'missing' => $missing,
            'voices' => $voices,
            'rounds' => $rounds,
            'calls' => $calls,
            'credits' => $calls * max(0, (int) $tool->credit_cost),
            'seed' => $seed,
            'clamped' => $clamped,
        ];
    }

    /**
     * Spend the plan: one call per voice per round, then assemble. A voice that fails after the first one answered does
     * not sink the panel — the run reports how many calls were answered and the controller charges only for those.
     *
     * @return array{markdown: string, title: string, voices: array, counts: array, decision: string, answered: int, partial: bool, tokens: int, provider: string, model: string, cached: bool, credits: int}
     */
    public function run(Tool $tool, array $plan, ?ToolVersion $version, AiContext $context): array
    {
        $caps = self::caps();
        $instructions = trim((string) ($version?->ai_instructions ?: self::INSTRUCTIONS));
        $context = new AiContext(
            workspace: $context->workspace,
            userId: $context->userId,
            runId: $context->runId,
            purpose: $context->purpose,
            operationKey: $context->operationKey,
            sensitive: true,
            cacheable: true,
            maxOutputTokens: $caps['output_tokens'],
            cacheMinutes: $caps['cache_minutes'],
        );

        $voices = [];
        $tokens = 0;
        $providers = [];
        $models = [];
        $cached = true;
        $failure = null;
        foreach ($plan['voices'] as $index => $persona) {
            try {
                $answer = $this->ask($persona, $plan, 1, [], null, $instructions, $context->forStep('v'.$index.'r1'));
            } catch (DomainException $exception) {
                $failure = $exception;
                break;
            }
            $tokens += $answer['tokens'];
            $providers[$answer['provider']] = true;
            $models[$answer['model']] = true;
            $cached = $cached && $answer['cached'];
            $voices[$index] = ['persona' => $persona, 'first' => $answer, 'second' => null];
        }

        if ($voices === []) {
            throw $failure ?? new DomainException('تعذّر تشغيل اللوحة. لم يُخصم أي رصيد.');
        }

        if ($plan['rounds'] > 1 && $failure === null) {
            $objections = array_values(array_filter(array_map(fn (array $v) => $v['first']['objection'], $voices)));
            foreach ($voices as $index => $voice) {
                try {
                    $answer = $this->ask($voice['persona'], $plan, 2, $objections, $voice['first'], $instructions, $context->forStep('v'.$index.'r2'));
                } catch (DomainException $exception) {
                    $failure = $exception;
                    break;
                }
                $tokens += $answer['tokens'];
                $providers[$answer['provider']] = true;
                $models[$answer['model']] = true;
                $cached = $cached && $answer['cached'];
                $voices[$index]['second'] = $answer;
            }
        }

        $answered = 0;
        foreach ($voices as $voice) {
            $answered += 1 + ($voice['second'] ? 1 : 0);
        }
        $final = array_map(fn (array $v) => $v['second'] ?? $v['first'], $voices);
        $counts = $this->counts($final);
        $decision = $this->decision($counts, count($final));

        // array_merge, not `+`: the computed values must win over a same-named raw input key.
        $values = array_merge($plan['input'], [
            'offer_short' => Str::limit((string) ($plan['input']['offer'] ?? ''), 50, '…'),
            'message' => (string) ($plan['input']['message'] ?? ''),
            'voices_count' => count($final),
            'rounds' => $plan['rounds'],
            'count_buy' => $counts['buy'],
            'count_maybe' => $counts['maybe'],
            'count_no' => $counts['no'],
            'count_unclear' => $counts['unclear'],
            'decision' => $decision,
            'voices_table' => $this->table($voices, 1),
            'round_two' => $plan['rounds'] > 1 ? $this->table($voices, 2) : 'جولة واحدة في هذا التشغيل: لم يُعرض على اللوحة اعتراض غيرها.',
            'objections' => $this->bullets($final, 'objection', 'لم يسجّل أي صوت اعتراضاً — وهذا بحد ذاته نتيجة تستحق التحقق مع عميل حقيقي.'),
            'unlocks' => $this->bullets($final, 'unlock', 'لم يحدّد أي صوت ما يقنعه؛ أعد صياغة الرسالة حول منفعة واحدة واضحة ثم أعد اللوحة.'),
            'seed' => $plan['seed'],
            'provider' => implode('، ', array_keys($providers)),
            'model' => implode('، ', array_keys($models)),
        ]);

        $template = $version?->output_template ?: (string) $tool->output_template;
        $markdown = $this->engine->renderTemplate($template, $values);

        return [
            'markdown' => $markdown,
            'title' => 'لوحة السوق المحاكى — '.Str::limit((string) ($plan['input']['offer'] ?? $tool->name), 40, '…'),
            'voices' => $voices,
            'counts' => $counts,
            'decision' => $decision,
            'answered' => $answered,
            'partial' => $failure !== null,
            'tokens' => $tokens,
            'provider' => implode('، ', array_keys($providers)),
            'model' => implode('، ', array_keys($models)),
            'cached' => $cached,
            'credits' => $answered * max(0, (int) $tool->credit_cost),
        ];
    }

    /** One voice, one round. */
    private function ask(string $persona, array $plan, int $round, array $objections, ?array $previous, string $instructions, AiContext $context): array
    {
        $input = $plan['input'];
        $prompt = "العرض: ".($input['offer'] ?? '—')."\n";
        $prompt .= 'القطاع: '.Catalog::label(Catalog::SECTORS, $input['sector'] ?? null, '—')."\n";
        $prompt .= 'السعر أو العرض المالي: '.(trim((string) ($input['price'] ?? '')) !== '' ? $input['price'] : 'غير مذكور')."\n\n";
        $prompt .= "أنت هذه الشخصية: {$persona}\n\n";
        $prompt .= "الرسالة المعروضة عليك:\n\"\"\"\n".($input['message'] ?? '')."\n\"\"\"\n";
        if ($round > 1) {
            $prompt .= "\nقرارك في الجولة الأولى كان: ".self::VERDICTS[$previous['verdict'] ?? 'unclear']." لأن: ".($previous['reason'] ?? '—')."\n";
            $prompt .= "ثم سمعت اعتراضات بقية اللوحة:\n- ".implode("\n- ", $objections ?: ['لا اعتراضات'])."\n";
            $prompt .= "\nهل يتغير قرارك؟ غيّره فقط إن كشف لك اعتراضٌ شيئاً لم تلاحظه. أجب بنفس بنية JSON.\n";
        }

        $answer = $this->ai->generate($prompt, $instructions, $context);

        return $this->parse($answer['text']) + [
            'tokens' => (int) $answer['tokens'],
            'provider' => (string) $answer['provider'],
            'model' => (string) $answer['model'],
            'cached' => (bool) $answer['cached'],
        ];
    }

    /**
     * A model that answers outside the agreed shape is a reading problem, not a reason to lose the voice: the text is
     * kept as the voice's reason and the verdict is marked «لم يتضح» so the count never silently invents a buyer.
     */
    private function parse(string $text): array
    {
        $body = trim(preg_replace('/^```(?:json)?|```$/mu', '', trim($text)) ?? $text);
        $start = strpos($body, '{');
        $end = strrpos($body, '}');
        $decoded = $start !== false && $end !== false && $end > $start
            ? json_decode(substr($body, $start, $end - $start + 1), true)
            : null;

        if (! is_array($decoded)) {
            return ['verdict' => 'unclear', 'reason' => Str::limit($body, 240, '…'), 'objection' => '', 'unlock' => ''];
        }

        $verdict = is_string($decoded['verdict'] ?? null) ? strtolower(trim($decoded['verdict'])) : '';

        return [
            'verdict' => isset(self::VERDICTS[$verdict]) && $verdict !== 'unclear' ? $verdict : 'unclear',
            'reason' => $this->text($decoded['reason'] ?? ''),
            'objection' => $this->text($decoded['objection'] ?? ''),
            'unlock' => $this->text($decoded['unlock'] ?? ''),
        ];
    }

    private function text(mixed $value): string
    {
        $value = is_array($value) ? implode('؛ ', array_filter($value, 'is_scalar')) : $value;

        return is_scalar($value) ? trim(str_replace(['|', "\n"], [' ', ' '], (string) $value)) : '';
    }

    /** @return array<string, int> */
    private function counts(array $final): array
    {
        $counts = array_fill_keys(array_keys(self::VERDICTS), 0);
        foreach ($final as $answer) {
            $counts[$answer['verdict']]++;
        }

        return $counts;
    }

    /** The verdict is a declared rule, like the simulator's risks — not a model's opinion about itself. */
    private function decision(array $counts, int $total): string
    {
        $share = fn (string $key) => $total > 0 ? $counts[$key] / $total * 100 : 0;
        if ($share('buy') >= 60 && $share('no') < 40) {
            return 'الرسالة مفهومة لأغلب اللوحة: اختبرها كما هي بـ10٪ من الميزانية لسبعة أيام، وقرّر على الطلبات الحقيقية لا على هذه الأصوات.';
        }
        if ($share('no') >= 40) {
            return 'لا تُنفق على هذه الرسالة بصيغتها الحالية: أعد صياغتها حول أقوى اعتراض أدناه، ثم أعد اللوحة وقارن الاعتراضات.';
        }
        if ($share('maybe') >= 50) {
            return 'الرسالة غير حاسمة: الشرط الذي تكرّر في «ما يقنعهم» ناقص من الرسالة نفسها. أضفه ثم أعد اللوحة.';
        }

        return 'اللوحة منقسمة: جهّز نسختين — (أ) الرسالة الحالية، (ب) الرسالة بعد معالجة أول اعتراض — واختبرهما بميزانية صغيرة قبل أي رفع.';
    }

    private function table(array $voices, int $round): string
    {
        $header = $round === 1
            ? "| الشخصية | القرار | السبب | الاعتراض | ما يقنعه |\n|---|---|---|---|---|"
            : "| الشخصية | القرار بعد سماع الاعتراضات | ما تغيّر |\n|---|---|---|";
        $rows = [];
        foreach ($voices as $voice) {
            $answer = $round === 1 ? $voice['first'] : $voice['second'];
            if (! $answer) {
                continue;
            }
            $persona = Str::limit($voice['persona'], 60, '…');
            if ($round === 1) {
                $rows[] = '| '.$persona.' | '.self::VERDICTS[$answer['verdict']].' | '.($answer['reason'] ?: '—').' | '.($answer['objection'] ?: '—').' | '.($answer['unlock'] ?: '—').' |';

                continue;
            }
            $moved = $answer['verdict'] === $voice['first']['verdict']
                ? 'لم يتغير القرار'
                : 'تحوّل من «'.self::VERDICTS[$voice['first']['verdict']].'» إلى «'.self::VERDICTS[$answer['verdict']].'»';
            $rows[] = '| '.$persona.' | '.self::VERDICTS[$answer['verdict']].' | '.$moved.($answer['reason'] ? ': '.$answer['reason'] : '').' |';
        }

        return $rows === [] ? 'لم تُسجّل أصوات في هذه الجولة.' : $header."\n".implode("\n", $rows);
    }

    private function bullets(array $final, string $key, string $empty): string
    {
        $lines = [];
        foreach ($final as $index => $answer) {
            if (trim((string) $answer[$key]) === '') {
                continue;
            }
            $lines[] = '- **صوت '.($index + 1).'** ('.self::VERDICTS[$answer['verdict']].'): '.$answer[$key];
        }

        return $lines === [] ? $empty : implode("\n", $lines);
    }

    /** @return array<int, string> */
    private function lines(string $value): array
    {
        $lines = preg_split('/\R+/u', $value) ?: [];
        $lines = array_map(fn ($line) => trim(preg_replace('/^[-•*\d.\s]+/u', '', (string) $line) ?? (string) $line), $lines);

        return array_values(array_filter($lines, fn ($line) => mb_strlen($line) >= 3));
    }
}
