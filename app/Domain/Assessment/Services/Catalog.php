<?php

namespace App\Domain\Assessment\Services;

/**
 * Shared vocabularies for the project profile: used by the home "try it now" form, the diagnosis,
 * the lesson composer and the admin screens. Keys are stored; labels are displayed.
 */
class Catalog
{
    public const AUDIENCES = ['owner' => 'صاحب مشروع', 'marketer' => 'مسوّق أو وكالة'];

    public const DIMENSIONS = [
        'goal' => 'الهدف والرقم',
        'audience' => 'الجمهور والشريحة',
        'offer' => 'العرض والرسالة',
        'channel' => 'القناة والتنفيذ',
        'measure' => 'القياس والتحسين',
    ];

    public const LEVELS = ['beginner' => 'مبتدئ', 'intermediate' => 'متوسط', 'advanced' => 'متقدم'];

    public const BUDGETS = [
        '0' => 'لا ميزانية إعلانية الآن',
        '500' => 'أقل من 1,000 ريال',
        '2000' => '1,000 – 3,000 ريال',
        '5000' => '3,000 – 8,000 ريال',
        '12000' => '8,000 – 20,000 ريال',
        '30000' => 'أكثر من 20,000 ريال',
    ];

    public const CHANNELS = [
        'instagram' => 'إنستغرام',
        'tiktok' => 'تيك توك',
        'snapchat' => 'سناب شات',
        'google' => 'بحث جوجل / إعلانات البحث',
        'whatsapp' => 'واتساب والبث المباشر للعملاء',
        'referrals' => 'توصيات العملاء',
        'offline' => 'المعرض والمحل والميدان',
        'none' => 'لا قناة ثابتة بعد',
    ];

    public const GOALS = [
        'leads' => 'طلبات أو محادثات مؤهلة',
        'sales' => 'مبيعات مباشرة',
        'awareness' => 'أن يعرفني الجمهور المناسب',
        'retention' => 'عودة العملاء الحاليين',
    ];

    public const SECTORS = [
        'retail' => 'تجزئة ومتجر إلكتروني',
        'food' => 'مطاعم وكافيهات وأغذية',
        'services' => 'خدمات مهنية واستشارات',
        'beauty' => 'تجميل وعناية',
        'education' => 'تعليم وتدريب',
        'health' => 'صحة وعيادات',
        'realestate' => 'عقار ومقاولات',
        'tech' => 'تقنية وبرمجيات',
        'other' => 'أخرى',
    ];

    public const PROBLEMS = [
        'unknown' => 'لا أحد يعرفني ولا تصلني طلبات',
        'no_sales' => 'متابعون وزيارات بلا مبيعات',
        'no_target' => 'لا أعرف من أستهدف بالضبط',
        'ads_waste' => 'الإعلانات تستهلك الميزانية بلا نتيجة واضحة',
        'price' => 'أخسر العملاء أمام منافس أرخص',
        'no_measure' => 'أنفّذ أشياء كثيرة ولا أعرف ما ينفع',
    ];

    /** Published benchmark assumptions: cost per qualified lead (SAR) by channel for Saudi SMBs. Editable per lesson in the studio. */
    public const COST_PER_LEAD = [
        'instagram' => 40, 'tiktok' => 35, 'snapchat' => 38, 'google' => 70, 'whatsapp' => 15, 'referrals' => 10, 'offline' => 90, 'none' => 60,
    ];

    public static function label(array $map, ?string $key, string $fallback = '—'): string
    {
        return $key !== null && isset($map[$key]) ? $map[$key] : $fallback;
    }

    public static function budgetBand(?int $budget): string
    {
        $budget = (int) $budget;
        if ($budget <= 0) {
            return '0';
        }
        foreach (['500' => 1000, '2000' => 3000, '5000' => 8000, '12000' => 20000] as $key => $max) {
            if ($budget < $max) {
                return $key;
            }
        }

        return '30000';
    }

    public static function levelFor(int $overall): string
    {
        return match (true) {
            $overall >= 75 => 'advanced',
            $overall >= 45 => 'intermediate',
            default => 'beginner',
        };
    }
}
