<?php

namespace App\Domain\Assessment\Services;

use App\Domain\Academy\Models\Lesson;

/**
 * Home-page "جرّب على مشروعك الآن" (blueprint §4.1 row 1): three fields → first step with a reason and a specific lesson.
 * Rules only, no AI, no account. The same rule table is exported to the page so the browser answers without a request.
 */
class FirstStepAdvisor
{
    /** problem key → [lesson slug, step template, reason template]. {offer} is the learner's own words. */
    public const RULES = [
        'unknown' => [
            'budget_zero' => ['first-segment', 'خلال 7 أيام تحدّث مع 10 أشخاص من الشريحة التي تشتري «{offer}» واسألهم عن بديلهم الحالي.', 'بلا ميزانية، أسرع طريق للطلبات هو شريحة واحدة ضيقة تعرف مشكلتها بالاسم، لا منشورات عامة.'],
            'default' => ['first-channel-and-budget', 'اختر قناة واحدة فقط لـ «{offer}» وخصّص لها 80٪ من الميزانية لأربعة أسابيع مع رقم هدف واحد.', 'توزيع الميزانية على عدة قنوات يُخفي ما ينفع فعلاً؛ قناة واحدة بهدف رقمي تعطيك إجابة خلال شهر.'],
        ],
        'no_sales' => ['core-message', 'أعد كتابة رسالتك الأساسية لـ «{offer}» في جملة واحدة: وعد + دليل + خطوة تالية، وضعها في أول ثلاث ثوانٍ من كل منشور وصفحة.', 'الزيارات بلا مبيعات غالباً مشكلة رسالة لا مشكلة وصول: الزائر لا يفهم لماذا أنت ولماذا الآن.'],
        'no_target' => ['first-segment', 'اختر شريحة أولى واحدة لـ «{offer}» بثلاثة أوصاف: من هي، ما المشكلة التي تدفعها للشراء، وأين تبحث عن الحل.', 'كل قرار تسويقي لاحق (الرسالة، القناة، السعر) يُبنى على شريحة واحدة واضحة؛ بدونها تصبح كل التجارب عشوائية.'],
        'ads_waste' => ['first-channel-and-budget', 'أوقف كل الإعلانات إلا قناة واحدة، وثبّت لها تكلفة مقبولة للطلب الواحد وقاعدة إيقاف بعد 14 يوماً.', 'الميزانية تضيع عندما لا يوجد رقم يحكم على الإعلان: تكلفة الطلب وقاعدة الإيقاف تحوّل الإنفاق إلى تجربة مقيسة.'],
        'price' => ['market-map', 'ارسم خريطة سوق «{offer}»: ثلاثة منافسين، وعد كل واحد، والفجوة التي لا يغطيها أحد، ثم تموضع فيها بدل منافسة السعر.', 'منافسة السعر تُخسر الجميع؛ الفجوة في وعود المنافسين هي المكان الذي يدفع فيه العميل أكثر عن رضا.'],
        'no_measure' => ['first-marketing-plan', 'اختر مؤشراً قيادياً واحداً ومؤشر نتيجة واحداً لـ «{offer}» وسجّلهما كل أسبوع في جدول من سطرين لمدة 4 أسابيع.', 'ما لا يُقاس أسبوعياً لا يمكن تحسينه؛ مؤشران فقط كافيان لمعرفة ما ينفع وما يُوقف.'],
    ];

    public function advise(string $offer, string $problem, string $budgetBand): array
    {
        $rule = self::RULES[$problem] ?? self::RULES['no_target'];
        if (isset($rule['default'])) {
            $rule = $budgetBand === '0' && isset($rule['budget_zero']) ? $rule['budget_zero'] : $rule['default'];
        }
        [$slug, $step, $reason] = $rule;
        $offer = trim($offer) !== '' ? trim(mb_substr($offer, 0, 80)) : 'مشروعك';
        $lesson = Lesson::query()->where('slug', $slug)->whereNotNull('published_at')->with('course')->first();

        return [
            'step' => str_replace('{offer}', $offer, $step),
            'reason' => $reason,
            'lesson' => $lesson,
            'lesson_url' => $lesson ? route('academy.lesson', [$lesson->course, $lesson]) : route('academy.index'),
            'lesson_title' => $lesson?->title ?? 'أساسيات التسويق وفهم السوق',
        ];
    }

    /** Rule table + lesson URLs for the browser (no network round-trip). */
    public function exportForBrowser(): array
    {
        $slugs = collect(self::RULES)->flatMap(fn ($r) => isset($r['default']) ? [$r['default'][0], $r['budget_zero'][0]] : [$r[0]])->unique();
        $lessons = Lesson::query()->whereIn('slug', $slugs)->whereNotNull('published_at')->with('course')->get()->keyBy('slug');
        $out = [];
        foreach (self::RULES as $problem => $rule) {
            $variants = isset($rule['default']) ? $rule : ['default' => $rule];
            foreach ($variants as $variant => [$slug, $step, $reason]) {
                $lesson = $lessons[$slug] ?? null;
                $out[$problem][$variant] = [
                    'step' => $step, 'reason' => $reason,
                    'url' => $lesson ? route('academy.lesson', [$lesson->course, $lesson]) : route('academy.index'),
                    'title' => $lesson?->title ?? 'أساسيات التسويق وفهم السوق',
                ];
            }
        }

        return $out;
    }
}
