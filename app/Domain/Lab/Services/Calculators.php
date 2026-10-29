<?php

namespace App\Domain\Lab\Services;

use InvalidArgumentException;

/**
 * The four calculators (blueprint §4.8). Pure functions: the page computes them instantly in the browser with the same
 * formulas (see the calculator view script) and the server computes them here for the shareable/copyable result and tests.
 *
 * Benchmarks are written explicitly with their source so the learner can disagree with them:
 *  - LTV:CAC ≥ 3 as the healthy floor for subscription/SMB businesses (David Skok, forEntrepreneurs "SaaS Metrics 2.0").
 *  - ROAS break-even = 1 / gross margin (standard unit-economics identity).
 *  - CAC payback ≤ 12 months (same source as LTV:CAC).
 *  - A/B sample size n ≈ 16·p(1−p)/d² per variant for α=0.05, power 0.8 (Evan Miller's calculator approximation of the two-proportion z-test).
 */
class Calculators
{
    public static function compute(string $slug, array $input): array
    {
        return match ($slug) {
            'cac' => self::cac($input),
            'ltv' => self::ltv($input),
            'roas' => self::roas($input),
            'conversion-sample' => self::conversion($input),
            default => throw new InvalidArgumentException('حاسبة غير معروفة.'),
        };
    }

    private static function num(array $input, string $key, float $default = 0): float
    {
        return is_numeric($input[$key] ?? null) ? (float) $input[$key] : $default;
    }

    public static function cac(array $i): array
    {
        $spend = self::num($i, 'spend');
        $customers = self::num($i, 'customers');
        $cac = $customers > 0 ? $spend / $customers : null;

        return [
            'headline' => $cac === null ? '—' : number_format($cac, 1).' ريال',
            'label' => 'تكلفة اكتساب العميل الواحد',
            'rows' => [['الإنفاق', number_format($spend).' ريال'], ['العملاء الجدد', number_format($customers)], ['CAC', $cac === null ? '—' : number_format($cac, 1).' ريال']],
            'meaning' => $cac === null ? 'أدخل عدد عملاء أكبر من صفر.' : 'كل عميل جديد كلّفك '.number_format($cac, 1).' ريالاً تسويقياً. الرقم بلا معنى وحده: قارنه بهامش الربح من العميل الواحد وبقيمته الدائمة (LTV).',
            'benchmark' => 'قاعدة إرشادية: CAC ≤ ثلث LTV، وأن يُستردّ خلال 12 شهراً من هامش العميل (SaaS Metrics 2.0، forEntrepreneurs).',
            'values' => ['cac' => $cac],
        ];
    }

    public static function ltv(array $i): array
    {
        $avg = self::num($i, 'avg_order');
        $perYear = self::num($i, 'orders_per_year');
        $years = self::num($i, 'years');
        $margin = self::num($i, 'margin') / 100;
        $cac = self::num($i, 'cac');
        $ltv = $avg * $perYear * $years * $margin;
        $ratio = $cac > 0 ? $ltv / $cac : null;
        $verdict = $ratio === null ? '' : ($ratio >= 3 ? 'نسبة صحية (≥ 3).' : ($ratio >= 1 ? 'نسبة ضعيفة (1–3): تربح لكن النمو بطيء ومكلف.' : 'تخسر على كل عميل: خفّض CAC أو ارفع التكرار والهامش.'));

        return [
            'headline' => number_format($ltv).' ريال',
            'label' => 'القيمة الدائمة للعميل (ربحاً إجمالياً)',
            'rows' => [['الإيراد من العميل', number_format($avg * $perYear * $years).' ريال'], ['هامش الربح', (int) round($margin * 100).'٪'], ['LTV', number_format($ltv).' ريال'], ['LTV : CAC', $ratio === null ? '— (أدخل CAC)' : number_format($ratio, 1).' : 1']],
            'meaning' => 'العميل الواحد يترك لك '.number_format($ltv).' ريالاً ربحاً إجمالياً طوال علاقته بك. '.$verdict.' أكبر رافعة عادةً هي عدد الطلبات في السنة، لا السعر.',
            'benchmark' => 'المعيار المرجعي: LTV ≥ 3 × CAC (David Skok, SaaS Metrics 2.0). أقل من 1 يعني أنك تدفع لتخسر.',
            'values' => ['ltv' => $ltv, 'ratio' => $ratio],
        ];
    }

    public static function roas(array $i): array
    {
        $revenue = self::num($i, 'revenue');
        $spend = self::num($i, 'spend');
        $margin = self::num($i, 'margin') / 100;
        $roas = $spend > 0 ? $revenue / $spend : null;
        $breakEven = $margin > 0 ? 1 / $margin : null;
        $verdict = $roas === null || $breakEven === null ? '' : ($roas >= $breakEven * 1.3 ? 'الإعلانات مربحة بعد التكلفة.' : ($roas >= $breakEven ? 'على نقطة التعادل تقريباً: أي ارتفاع في التكلفة يحوّلها إلى خسارة.' : 'الإعلانات تخسر: كل ريال إعلان يعيد أقل من تكلفته بعد هامش الربح.'));

        return [
            'headline' => $roas === null ? '—' : number_format($roas, 2).'×',
            'label' => 'العائد على الإنفاق الإعلاني',
            'rows' => [['الإيراد', number_format($revenue).' ريال'], ['الإنفاق', number_format($spend).' ريال'], ['ROAS', $roas === null ? '—' : number_format($roas, 2).'×'], ['نقطة التعادل', $breakEven === null ? '—' : number_format($breakEven, 2).'× (1 ÷ الهامش)']],
            'meaning' => $roas === null ? 'أدخل إنفاقاً أكبر من صفر.' : 'كل ريال إعلان أعاد '.number_format($roas, 2).' ريالاً إيراداً. '.$verdict,
            'benchmark' => 'نقطة التعادل = 1 ÷ هامش الربح الإجمالي (بهامش 40٪ تحتاج ROAS 2.5× لتصل إلى الصفر). المعيار العملي للمتاجر الصغيرة: ≥ 1.3 × نقطة التعادل.',
            'values' => ['roas' => $roas, 'break_even' => $breakEven],
        ];
    }

    public static function conversion(array $i): array
    {
        $visitors = self::num($i, 'visitors');
        $conversions = self::num($i, 'conversions');
        $lift = self::num($i, 'lift') / 100;
        $rate = $visitors > 0 ? $conversions / $visitors : null;
        $sample = null;
        if ($rate !== null && $rate > 0 && $rate < 1 && $lift > 0) {
            $d = $rate * $lift;
            $sample = (int) ceil(16 * $rate * (1 - $rate) / ($d * $d));
        }

        return [
            'headline' => $rate === null ? '—' : number_format($rate * 100, 2).'٪',
            'label' => 'معدل التحويل',
            'rows' => [['الزيارات', number_format($visitors)], ['التحويلات', number_format($conversions)], ['معدل التحويل', $rate === null ? '—' : number_format($rate * 100, 2).'٪'], ['العينة لكل نسخة', $sample === null ? '—' : number_format($sample).' زيارة']],
            'meaning' => $rate === null ? 'أدخل زيارات أكبر من صفر.' : 'من كل 100 زائر يتحوّل '.number_format($rate * 100, 1).'. لكشف تحسين نسبي قدره '.(int) round($lift * 100).'٪ تحتاج '.($sample === null ? '—' : number_format($sample)).' زيارة لكل نسخة قبل أن تحكم؛ قبل ذلك الفرق ضجيج.',
            'benchmark' => 'معدل تحويل صفحات الهبوط الإعلانية للمشاريع الصغيرة: 2–5٪ معيار عملي. حجم العينة ≈ 16·p(1−p)/d² لكل نسخة (α=0.05، قوة 0.8؛ تقريب Evan Miller لاختبار النسبتَين).',
            'values' => ['rate' => $rate, 'sample' => $sample],
        ];
    }
}
