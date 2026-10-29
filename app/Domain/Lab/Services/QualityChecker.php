<?php

namespace App\Domain\Lab\Services;

use App\Domain\Assessment\Services\Catalog;

/**
 * فحص قابلية التنفيذ (blueprint §4.7): six declared rules run on every generated asset.
 * A tool's quality_rules says which are required (affect the verdict) and which are advisory;
 * rules a tool does not list are shown as "لا ينطبق" so the learner always sees the same six items.
 */
class QualityChecker
{
    public const CHECKS = [
        'goal_number_date' => 'هدف برقم وتاريخ',
        'single_audience' => 'جمهور واحد',
        'metric_stop_rule' => 'مقياس وقاعدة إيقاف',
        'single_channel' => 'قناة واحدة',
        'risk_mitigation' => 'خطر وحلّه',
        'budget_consistency' => 'اتساق الميزانية مع الهدف',
    ];

    public const VERDICTS = ['within' => 'قابل للتنفيذ', 'edge' => 'يحتاج تصحيحاً', 'outside' => 'غير قابل للتنفيذ بعد'];

    /** @return array{checks: array<int, array{key:string,label:string,ok:bool,applies:bool,level:string,hint:string,field:?string}>, passed:int, total:int, verdict:string, verdict_label:string} */
    public function check(string $markdown, array $values, array $rules = [], array $fields = []): array
    {
        $required = $rules['required'] ?? null;
        $advisory = $rules['advisory'] ?? [];
        $all = $required === null && $advisory === [];
        $fieldKeys = array_column($fields, 'key');
        $text = $markdown.' '.implode(' ', array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $values));
        $checks = [];
        $requiredFailures = 0;
        $passed = 0;
        foreach (self::CHECKS as $key => $label) {
            $level = $all || in_array($key, $required ?? [], true) ? 'required' : (in_array($key, $advisory, true) ? 'advisory' : 'none');
            [$ok, $hint, $field] = $this->run($key, $markdown, $text, $values, $fieldKeys);
            if ($level === 'none') {
                $ok = true;
                $hint = 'لا ينطبق على هذه الأداة.';
            }
            if ($ok) {
                $passed++;
            } elseif ($level === 'required') {
                $requiredFailures++;
            }
            $checks[] = ['key' => $key, 'label' => $label, 'ok' => $ok, 'applies' => $level !== 'none', 'level' => $level, 'hint' => $hint, 'field' => $ok ? null : $field];
        }
        $verdict = $requiredFailures === 0 ? 'within' : ($requiredFailures === 1 ? 'edge' : 'outside');

        return ['checks' => $checks, 'passed' => $passed, 'total' => count($checks), 'verdict' => $verdict, 'verdict_label' => self::VERDICTS[$verdict]];
    }

    /** @return array{0:bool,1:string,2:?string} */
    private function run(string $key, string $markdown, string $text, array $values, array $fieldKeys): array
    {
        return match ($key) {
            'goal_number_date' => $this->goalNumberDate($values, $markdown),
            'single_audience' => $this->singleAudience($values, $markdown),
            'metric_stop_rule' => [
                (bool) preg_match('/مقياس|مؤشر|KPI|معدل|تكلفة الطلب/u', $text) && (bool) preg_match('/أوقف|إيقاف|توقف|قاعدة القرار|الحد الأدنى/u', $text),
                'أضف مقياساً واحداً (مثل تكلفة الطلب) وقاعدة إيقاف برقم وتاريخ.', null,
            ],
            'single_channel' => $this->singleChannel($values, $markdown, $fieldKeys),
            'risk_mitigation' => [
                (bool) preg_match('/خطر|مخاطر/u', $text) && (bool) preg_match('/الحل|حلّه|حله|التخفيف|بديل|نعالج/u', $text),
                'اكتب خطراً واحداً متوقعاً وما ستفعله إن وقع.', in_array('risk', $fieldKeys, true) ? 'risk' : null,
            ],
            'budget_consistency' => $this->budgetConsistency($values, $fieldKeys),
            default => [true, '', null],
        };
    }

    private function goalNumberDate(array $values, string $markdown): array
    {
        $goal = (string) ($values['goal'] ?? '');
        $subject = trim($goal) !== '' && ! isset(Catalog::GOALS[$goal]) ? $goal : $markdown;
        $hasNumber = (bool) preg_match('/\d|[٠-٩]/u', $subject);
        $hasTime = (bool) preg_match('/يوم|أيام|أسبوع|أسابيع|شهر|أشهر|شهور|ربع|سنة|خلال|بحلول|قبل\s+\d|20\d\d|\d{1,2}\/\d{1,2}/u', $subject);

        return [$hasNumber && $hasTime, $hasNumber ? 'أضف موعداً أو مدة إلى الهدف (مثال: خلال 30 يوماً).' : 'اكتب الهدف برقم وتاريخ (مثال: 60 طلباً خلال 30 يوماً).', 'goal'];
    }

    private function singleAudience(array $values, string $markdown): array
    {
        $audience = trim((string) ($values['audience'] ?? ''));
        if ($audience === '') {
            return [false, 'حدّد شريحة واحدة بجملة واحدة.', 'audience'];
        }
        $multiple = preg_match('/،|,|\sأو\s|\sو\s|\sو(?=ال)|كل الناس|الجميع|كل من|جميع/u', $audience) === 1;

        return [! $multiple, 'الجمهور يجمع أكثر من شريحة؛ أبقِ شريحة واحدة واحفظ الأخرى لأصل ثانٍ.', 'audience'];
    }

    private function singleChannel(array $values, string $markdown, array $fieldKeys): array
    {
        if (in_array('channel', $fieldKeys, true)) {
            $channel = (string) ($values['channel'] ?? '');
            $ok = isset(Catalog::CHANNELS[$channel]) && $channel !== 'none';

            return [$ok, $channel === 'none' ? 'اختر قناة واحدة لتبدأ بها بدلاً من «لا قناة ثابتة».' : 'اختر قناة واحدة.', 'channel'];
        }
        $mentions = 0;
        foreach (Catalog::CHANNELS as $key => $label) {
            if ($key !== 'none' && mb_stripos($markdown, $label) !== false) {
                $mentions++;
            }
        }

        return [$mentions <= 1, 'الأصل يذكر أكثر من قناة؛ ركّز على قناة واحدة.', null];
    }

    private function budgetConsistency(array $values, array $fieldKeys): array
    {
        $budget = (float) ($values['budget'] ?? 0);
        if ($budget <= 0) {
            return [in_array('budget', $fieldKeys, true) ? false : true, 'أدخل الميزانية الشهرية لمقارنتها بالهدف.', 'budget'];
        }
        $goalNumber = (int) ($values['goal_number'] ?? 0) ?: Recipes::firstNumber((string) ($values['goal'] ?? ''));
        if ($goalNumber <= 0) {
            return [false, 'اكتب الهدف برقم لتُقارَن به الميزانية.', 'goal'];
        }
        $cpl = Catalog::COST_PER_LEAD[(string) ($values['channel'] ?? '')] ?? 60;
        $expected = (int) floor($budget / $cpl);
        $ok = $expected >= 0.6 * $goalNumber;

        return [$ok, "الميزانية تغطي {$expected} طلباً من {$goalNumber} بتكلفة مرجعية {$cpl} ريالاً؛ ارفعها إلى ".number_format($goalNumber * $cpl).' ريال أو خفّض الهدف.', 'budget'];
    }
}
