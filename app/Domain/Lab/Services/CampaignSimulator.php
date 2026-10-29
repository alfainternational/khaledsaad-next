<?php

namespace App\Domain\Lab\Services;

use App\Domain\Assessment\Services\Catalog;

/**
 * محاكي الحملة الإعلانية (blueprint §4.6 item 4): budget + audience + channel → reach, clicks, leads, CPL and risks,
 * with the rules declared on the page. Benchmarks are explicit assumptions for Saudi SMBs, derived from published averages:
 *  - CPM/CTR: WordStream "Facebook Ads Benchmarks 2024" (avg CTR ≈ 1.5%, CPM ≈ $8–12) and "Google Ads Benchmarks 2024"
 *    (search avg CTR ≈ 6%, avg CPC ≈ $4), adjusted to SAR and to the lower CPMs observed in GCC markets; TikTok/Snap from
 *    their own 2024 advertiser guides (lower CPM, lower CTR). WhatsApp broadcast = messaging cost per 1,000 ≈ 5 SAR, high CTR.
 *  - Ad fatigue: frequency above 3 per month per person (Meta Business Help, "frequency" guidance).
 *  - Learning phase: ≥ 30 SAR/day for stable delivery (lesson «القناة الأولى والميزانية», stage 1).
 * Edit here, not in the view.
 */
class CampaignSimulator
{
    /** channel => [CPM in SAR, CTR as a fraction] */
    public const BENCHMARKS = [
        'instagram' => [25, 0.010],
        'tiktok' => [18, 0.009],
        'snapchat' => [15, 0.008],
        'google' => [120, 0.035],
        'whatsapp' => [5, 0.080],
    ];

    public const RANGE = 0.3; // ±30% around the point estimate

    public static function simulate(array $input): array
    {
        $budget = max(0, (float) ($input['budget'] ?? 0));
        $audience = max(1, (float) ($input['audience_size'] ?? 1));
        $channel = (string) ($input['channel'] ?? 'instagram');
        $paid = isset(self::BENCHMARKS[$channel]);
        [$cpm, $ctr] = self::BENCHMARKS[$channel] ?? self::BENCHMARKS['instagram'];
        $cvr = max(0, min(100, (float) ($input['cvr'] ?? 3))) / 100;

        $impressions = $cpm > 0 ? $budget / $cpm * 1000 : 0;
        $reach = min($audience, $impressions / 1.8); // ~1.8 impressions per reached person in a month is a typical delivery pattern
        $clicks = $impressions * $ctr;
        $leads = $clicks * $cvr;
        $cpl = $leads > 0 ? $budget / $leads : null;
        $frequency = $reach > 0 ? $impressions / $reach : 0;
        $daily = $budget / 30;
        $coverage = $audience > 0 ? min(100, $reach / $audience * 100) : 0;

        $fmtRange = fn (float $v, int $dec = 0) => number_format($v * (1 - self::RANGE), $dec).' – '.number_format($v * (1 + self::RANGE), $dec);
        $rows = [
            ['الظهورات (impressions)', number_format($impressions), $fmtRange($impressions)],
            ['الوصول (أشخاص)', number_format($reach), $fmtRange($reach)],
            ['النقرات', number_format($clicks), $fmtRange($clicks)],
            ['الطلبات المتوقعة', number_format($leads, 1), $fmtRange($leads, 1)],
            ['تكلفة الطلب (CPL)', $cpl === null ? '—' : number_format($cpl, 1).' ريال', $cpl === null ? '—' : number_format($cpl / (1 + self::RANGE), 1).' – '.number_format($cpl / (1 - self::RANGE), 1).' ريال'],
            ['التكرار (ظهور/شخص)', number_format($frequency, 1), '—'],
            ['الميزانية اليومية', number_format($daily, 1).' ريال', '—'],
        ];

        $risks = [];
        if ($daily < 30) {
            $risks[] = ['عالٍ', 'الميزانية اليومية أقل من 30 ريالاً: الحملة تبقى في مرحلة التعلم بلا نتائج مستقرة.', 'ركّز الميزانية في أسبوعين بدل شهر، أو ابدأ بقناة عضوية/مباشرة.'];
        }
        if ($frequency > 3) {
            $risks[] = ['عالٍ', 'التكرار '.number_format($frequency, 1).' ظهور لكل شخص: إجهاد إعلاني وارتفاع التكلفة بعد الأسبوع الثاني.', 'وسّع الجمهور إلى '.number_format($impressions / 1.8 / 2.5).' شخصاً على الأقل أو خفّض الميزانية.'];
        }
        if ($leads < 10) {
            $risks[] = ['متوسط', 'أقل من 10 طلبات متوقعة: العينة لا تكفي للحكم على الرسالة أو القناة.', 'مدّد الفترة أو ارفع الميزانية حتى تبلغ 10 طلبات على الأقل قبل أي قرار.'];
        }
        if ($coverage > 80) {
            $risks[] = ['متوسط', 'الوصول يغطي أكثر من 80٪ من الجمهور: ستكرّر على نفس الأشخاص سريعاً.', 'جهّز جمهوراً ثانياً مشابهاً (lookalike) للأسبوع الثالث.'];
        }
        if (! $paid) {
            $risks[] = ['ملاحظة', 'القناة المختارة ليست قناة إعلان مدفوع في هذا المحاكي؛ استُخدمت معايير إنستغرام تقريبياً.', 'اختر إنستغرام أو تيك توك أو سناب أو بحث جوجل أو واتساب لنتيجة أدق.'];
        }
        $cplBench = Catalog::COST_PER_LEAD[$channel] ?? 60;
        if ($cpl !== null) {
            $risks[] = [$cpl <= $cplBench ? 'منخفض' : 'متوسط', 'تكلفة الطلب المتوقعة '.number_format($cpl, 1).' ريال مقابل مرجعية القناة '.$cplBench.' ريالاً.', $cpl <= $cplBench ? 'ضمن المرجعية: التزم بقاعدة القرار بعد 14 يوماً.' : 'ارفع معدل التحويل (صفحة هبوط أوضح) قبل رفع الميزانية.'];
        }

        return [
            'channel' => $channel, 'channel_label' => Catalog::label(Catalog::CHANNELS, $channel), 'paid' => $paid,
            'rows' => $rows, 'risks' => $risks,
            'values' => ['impressions' => $impressions, 'reach' => $reach, 'clicks' => $clicks, 'leads' => $leads, 'cpl' => $cpl, 'frequency' => $frequency, 'daily' => $daily],
            'assumptions' => ['cpm' => $cpm, 'ctr' => $ctr * 100, 'cvr' => $cvr * 100, 'impressions_per_person' => 1.8],
            'rules' => [
                'الظهورات = الميزانية ÷ CPM × 1000.',
                'الوصول = الظهورات ÷ 1.8 (ولا يتجاوز حجم الجمهور).',
                'النقرات = الظهورات × CTR؛ الطلبات = النقرات × معدل التحويل.',
                'النطاق = ±30٪ حول التقدير؛ المخاطر تُطلق بالقواعد: < 30 ريال/يوم، تكرار > 3، < 10 طلبات، تغطية > 80٪.',
            ],
        ];
    }
}
