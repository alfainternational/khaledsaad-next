<?php

namespace App\Domain\Academy\Services;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Lab\Services\AiGateway;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use DomainException;
use Illuminate\Support\Str;

/**
 * المدرّب المحصور (blueprint §4.5 principle 6): answers only from the lesson content and the learner's own asset,
 * costs 1 credit, optional comparison with two anonymised assets from other learners. Available only with an AI key.
 */
class LessonCoach
{
    public function __construct(private readonly AiGateway $ai, private readonly CreditLedger $ledger) {}

    public static function enabled(): bool
    {
        return filled(config('services.openai.key'));
    }

    public function ask(User $user, Workspace $workspace, Lesson $lesson, string $question, ?ToolOutput $asset, bool $compare): array
    {
        if (! self::enabled()) {
            throw new DomainException('المدرّب غير متاح حالياً.');
        }
        $examples = $compare ? $this->anonymousExamples($lesson, $user) : [];
        $context = "عنوان الدرس: {$lesson->title}\nالهدف: {$lesson->goal}\n\nالمفهوم:\n".Str::limit((string) $lesson->concept_body, 6000)."\n\nالخطأ الشائع: {$lesson->common_mistake}\n";
        if ($asset) {
            $context .= "\nأصل المتعلم:\n".Str::limit((string) $asset->body_markdown, 3000)."\n";
        }
        foreach ($examples as $i => $example) {
            $context .= "\nمثال مجهول ".($i + 1)." من متعلم آخر:\n".$example."\n";
        }
        $instructions = 'أنت مدرّب تسويق عربي محصور في محتوى هذا الدرس وأصل المتعلم فقط. أجب بإيجاز عملي (≤ 150 كلمة) وبالعربية. '
            .'إن كان السؤال خارج نطاق الدرس فقل ذلك بجملة واحدة واقترح الجزء المناسب من الدرس. لا تخترع أرقاماً أو مصادر.';
        $key = 'coach:'.$lesson->id.':'.$user->id.':'.Str::random(8);
        $this->ledger->reserve($workspace, $key.':reserve', 1, 'سؤال للمدرّب: '.$lesson->title);
        try {
            $answer = $this->ai->generate("السياق:\n{$context}\n\nسؤال المتعلم: ".Str::limit($question, 800), $instructions);
        } catch (DomainException $e) {
            $this->ledger->record($workspace, $key.':release', 'release', 1, 'فك حجز سؤال فاشل');
            throw $e;
        }
        $this->ledger->record($workspace, $key.':release', 'release', 1, 'تحويل الحجز إلى استخدام');
        $this->ledger->record($workspace, $key.':capture', 'capture', -1, 'سؤال للمدرّب: '.$lesson->title);

        return ['answer' => $answer['text'], 'examples' => $examples];
    }

    /** Two short, anonymised excerpts of other learners' assets for this lesson. */
    public function anonymousExamples(Lesson $lesson, User $user): array
    {
        return ToolOutput::query()->where('lesson_id', $lesson->id)->where('user_id', '!=', $user->id)->inRandomOrder()->limit(2)->get()
            ->map(fn (ToolOutput $o) => Str::limit(trim(preg_replace('/^#.*$/m', '', (string) $o->body_markdown) ?? ''), 320))
            ->filter()->values()->all();
    }
}
