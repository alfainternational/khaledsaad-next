<?php

namespace App\Domain\Lab\Services;

use App\Domain\Assessment\Services\Catalog;
use Illuminate\Support\Str;

/**
 * Per-tool derived values for the base (no-AI) templates: tables, lists, scores and benchmark arithmetic.
 * Every rule is explicit here so the studio can read it and the learner can trust it.
 */
class Recipes
{
    public function compute(string $slug, array $values, array $input): array
    {
        return match ($slug) {
            'customer-persona' => $this->persona($values),
            'value-proposition' => $this->valueProposition($values),
            'campaign-brief-builder' => $this->campaignBrief($values),
            'content-angle-generator' => $this->contentAngles($values),
            'headlines-cta' => $this->headlines($values),
            'content-calendar' => $this->calendar($values),
            'email-sequence' => $this->emails($values),
            'utm-builder' => $this->utm($values),
            'landing-page-reviewer' => $this->landingReview($values),
            'prompt-builder' => $this->prompts($values),
            default => [],
        };
    }

    private function persona(array $v): array
    {
        $audience = (string) ($v['audience'] ?? '');
        $objection = trim((string) ($v['objection'] ?? ''));

        return [
            'persona_name' => Str::limit($audience, 48, '…'),
            'objection_line' => $objection !== '' ? $objection : 'لم يُحدَّد اعتراض — اسأل أول 5 عملاء لم يشتروا',
            'objection_short' => $objection !== '' ? Str::limit($objection, 40, '…') : 'الاعتراض الأكبر',
        ];
    }

    private function valueProposition(array $v): array
    {
        $alt = trim((string) ($v['alternative'] ?? ''));

        return [
            'alternative_line' => $alt !== '' ? $alt : 'البدائل المعتادة',
            'pain_short' => Str::limit((string) ($v['pain'] ?? ''), 50, '…'),
            'proof_short' => Str::limit((string) ($v['proof'] ?? ''), 50, '…'),
        ];
    }

    private function campaignBrief(array $v): array
    {
        $channel = (string) ($v['channel'] ?? 'none');
        $cpl = Catalog::COST_PER_LEAD[$channel] ?? 60;
        $budget = (float) ($v['budget'] ?? 0);
        $expected = $cpl > 0 ? (int) floor($budget / $cpl) : 0;
        $goalNumber = self::firstNumber((string) ($v['goal'] ?? ''));
        $risk = trim((string) ($v['risk'] ?? ''));
        if ($goalNumber > 0 && $expected > 0) {
            $ratio = $expected / $goalNumber;
            $verdict = $ratio >= 1
                ? "**ضمن الهدف**: الميزانية تغطي {$expected} طلباً مقابل {$goalNumber} مطلوباً."
                : ($ratio >= 0.6
                    ? "**على الحافة**: الميزانية تغطي {$expected} طلباً من {$goalNumber}؛ خفّض تكلفة الطلب بشريحة أدق أو ارفع الميزانية إلى ".number_format($goalNumber * $cpl).' ريال.'
                    : "**خارج الهدف**: الميزانية تغطي {$expected} طلباً فقط من {$goalNumber}. إما ميزانية ".number_format($goalNumber * $cpl).' ريال، أو هدف أصغر، أو قناة أرخص.');
        } else {
            $verdict = 'اكتب الهدف برقم (مثال: 60 طلباً) ليُقارَن بالطلبات المتوقعة.';
        }

        return [
            'cpl' => $cpl, 'expected_leads' => $expected, 'daily_budget' => (int) round($budget / 30), 'budget_fmt' => number_format($budget),
            'stop_cpl' => (int) round($cpl * 1.5), 'budget_verdict' => $verdict, 'goal_number' => $goalNumber,
            'goal_short' => Str::limit((string) ($v['goal'] ?? ''), 50, '…'),
            'risk_line' => $risk !== '' ? $risk : 'تكلفة الطلب أعلى من المرجعية ('.$cpl.' ريال) في الأسبوعين الأولين.',
            'risk_fix' => $risk !== '' ? 'اكتب حدّه رقماً، وراجعه يوم 14 بالقاعدة الثلاثية: أوقف، عدّل، أو ضاعف.' : 'جرّب رسالة ثانية على الشريحة نفسها قبل تغيير القناة، وأوقف عند '.(int) round($cpl * 1.5).' ريالاً.',
        ];
    }

    private function contentAngles(array $v): array
    {
        $offer = (string) ($v['offer'] ?? '');
        $audience = (string) ($v['audience'] ?? '');
        $pain = Str::limit((string) ($v['pain'] ?? ''), 60, '…');
        $objection = trim((string) ($v['objection'] ?? '')) ?: 'هل يناسبني؟';
        $rows = [
            ['اكتشاف', "الخطأ الذي يقع فيه أغلب {$audience} مع {$pain}", 'منشور تعليمي قصير', 'احفظ المنشور', 'حفظ/مشاركة'],
            ['اكتشاف', "لماذا يحدث «{$pain}» أصلاً؟ الشرح في 60 ثانية", 'فيديو قصير', 'تابع للسلسلة', 'مشاهدة كاملة'],
            ['اكتشاف', "3 علامات تدل على أنك تحتاج {$offer}", 'كاروسيل', 'أي علامة لديك؟', 'تعليق'],
            ['تقييم', "«{$objection}» — الإجابة الصريحة", 'منشور نصي + صورة', 'اسأل في الرسائل', 'رسالة'],
            ['تقييم', "قبل/بعد: ما الذي يتغيّر مع {$offer} ومتى", 'قبل وبعد', 'اطلب التفاصيل', 'رسالة'],
            ['تقييم', "ما لا يناسبه {$offer} (نقول لمن لا نناسبه)", 'منشور صريح', 'تأكد إن كنت الشريحة', 'حفظ'],
            ['قرار', "تجربة عميلة: من «{$pain}» إلى النتيجة", 'شهادة/قصة', 'ابدأ مثلها', 'نقرة/طلب'],
            ['قرار', "العرض الآن: {$offer} + الضمان، لـ{$audience} فقط", 'منشور العرض', 'اطلب الآن', 'طلب'],
        ];
        $table = "| المرحلة | الزاوية (الخطّاف) | الصيغة | الدعوة | المؤشر |\n|---|---|---|---|---|\n";
        foreach ($rows as $r) {
            $table .= '| '.implode(' | ', array_map(fn ($c) => str_replace('|', '/', $c), $r))." |\n";
        }

        return ['angles_table' => rtrim($table)];
    }

    private function headlines(array $v): array
    {
        $offer = (string) ($v['offer'] ?? '');
        $audience = (string) ($v['audience'] ?? '');
        $benefit = (string) ($v['benefit'] ?? '');
        $objection = trim((string) ($v['objection'] ?? ''));
        $channel = (string) ($v['channel'] ?? 'instagram');
        $headlines = [
            "{$benefit} — لـ{$audience}",
            "كيف تحصل على {$benefit} بلا تجارب فاشلة؟",
            "7 أيام تكفي: {$offer}",
            "تعبت من البدائل التي لا تنفع؟ جرّب {$offer}",
            "لـ{$audience} فقط: {$benefit}",
            "لماذا يختار {$audience} {$offer}؟ السبب في سطر",
            "الخطأ الذي يمنعك من {$benefit} (وكيف تتجنبه)",
            $objection !== '' ? "«{$objection}» — إليك الإجابة الصريحة" : "ما الذي يميّز {$offer} فعلاً؟",
            "جرّب {$offer} 14 يوماً وقرّر بنفسك",
            "{$offer}: النتيجة قبل الوعد",
        ];
        $ctas = match ($channel) {
            'whatsapp' => ['اكتب «أريد التفاصيل» وسنرد خلال ساعة', 'احجز موعدك بالرد على هذه الرسالة', 'أرسل صورة حالتك لنقترح الأنسب', 'اطلب الآن واستلم خلال 3 أيام', 'اسأل سؤالاً واحداً قبل أن تقرر'],
            'google' => ['اطلب الآن', 'احصل على استشارة مجانية', 'شاهد الأسعار', 'احجز موعداً اليوم', 'اطلب عرض سعر'],
            default => ['اطلب الآن', 'أرسل لنا رسالة', 'احفظ المنشور لتعود إليه', 'شاهد التفاصيل في الرابط', 'جرّب 14 يوماً مع ضمان الاسترجاع'],
        };
        $list = '';
        foreach ($headlines as $i => $h) {
            $list .= ($i + 1).'. '.$h."\n";
        }
        $ctaList = '';
        $when = ['للطلب المباشر', 'لبدء محادثة', 'لمحتوى الاكتشاف', 'لإعلان بصورة أو فيديو', 'لتجاوز خوف المخاطرة'];
        foreach ($ctas as $i => $c) {
            $ctaList .= '- **'.$c.'** — '.$when[$i]."\n";
        }

        return ['headlines_list' => rtrim($list), 'cta_list' => rtrim($ctaList)];
    }

    private function calendar(array $v): array
    {
        $weeks = max(1, min(8, (int) ($v['weeks'] ?? 4)));
        $perWeek = max(2, min(5, (int) ($v['posts_per_week'] ?? 3)));
        $pillars = ['تعليمي', 'دليل اجتماعي', 'خلف الكواليس', 'العرض', 'تفاعل/سؤال'];
        $ctas = ['احفظ المنشور', 'اطلب التفاصيل', 'تابع', 'اطلب الآن', 'أجب في التعليقات'];
        $metrics = ['حفظ', 'رسائل', 'وصول', 'نقرات/طلبات', 'تعليقات'];
        $days = ['السبت', 'الاثنين', 'الأربعاء', 'الأحد', 'الثلاثاء'];
        $start = now()->startOfWeek(\Carbon\Carbon::SATURDAY);
        $table = "| الأسبوع | اليوم | الركيزة | الفكرة | الدعوة | المؤشر |\n|---|---|---|---|---|---|\n";
        $k = 0;
        $offer = (string) ($v['offer'] ?? '');
        $audience = (string) ($v['audience'] ?? '');
        $ideas = [
            "خطأ شائع لدى {$audience} وكيف تتجنبه",
            "تقييم عميلة أو رقم حقيقي عن {$offer}",
            "كيف نصنع/نقدّم {$offer} (لقطة حقيقية)",
            "{$offer} + ما الذي يحصل عليه العميل بالضبط",
            "سؤال واحد لـ{$audience}: ما أكبر عائق لديك؟",
        ];
        for ($w = 1; $w <= $weeks; $w++) {
            for ($p = 0; $p < $perWeek; $p++) {
                $i = $k % 5;
                $date = $start->copy()->addWeeks($w - 1)->addDays([0, 2, 4, 1, 3][$p])->format('m/d');
                $table .= "| {$w} | {$days[$p]} {$date} | {$pillars[$i]} | {$ideas[$i]} | {$ctas[$i]} | {$metrics[$i]} |\n";
                $k++;
            }
        }
        $goal = (string) ($v['goal'] ?? 'leads');
        $metricLabel = match ($goal) { 'sales' => 'النقرات إلى صفحة الطلب', 'awareness' => 'الوصول إلى غير المتابعين', 'retention' => 'ردود العملاء الحاليين', default => 'الرسائل والمحادثات المؤهلة' };
        $floor = match ($goal) { 'sales' => '30 نقرة أسبوعياً', 'awareness' => '30٪ من الوصول لغير المتابعين', 'retention' => '10 ردود أسبوعياً', default => '10 محادثات مؤهلة أسبوعياً' };

        return [
            'calendar_table' => rtrim($table), 'total_posts' => $weeks * $perWeek, 'weeks' => $weeks,
            'start_date' => $start->format('Y/m/d'), 'end_date' => $start->copy()->addWeeks($weeks)->subDay()->format('Y/m/d'),
            'metric_label' => $metricLabel, 'metric_floor' => $floor,
        ];
    }

    private function emails(array $v): array
    {
        $days = max(5, (int) ($v['days'] ?? 7));
        $offer = (string) ($v['offer'] ?? '');
        $pain = (string) ($v['pain'] ?? '');
        $proof = (string) ($v['proof'] ?? '');
        $goal = (string) ($v['goal'] ?? 'leads');
        $schedule = [0, (int) round($days * 0.2), (int) round($days * 0.45), (int) round($days * 0.7), $days];
        $rows = [
            ['ترحيب', "أهلاً — إليك ما ستحصل عليه", "من نحن في سطرين، ما الذي سيصل خلال {$days} أيام، سؤال واحد: ما أكبر عائق لديك مع ".Str::limit($pain, 40, '…').'؟', 'ردّ على هذه الرسالة', 'معدل الرد'],
            ['المشكلة', 'لماذا لا تنفع الحلول المعتادة', "وصف {$pain} بكلمات القارئ، السبب الجذري، ما الذي جرّبه الناس ولم يعمل ولماذا.", 'اقرأ القصة كاملة', 'معدل النقر'],
            ['الدليل', 'ما حدث فعلاً مع من جرّب', "قصة عميل واحد من المشكلة إلى النتيجة + {$proof}.", 'شاهد التقييمات', 'معدل النقر'],
            ['العرض', "{$offer} — التفاصيل والضمان", 'ما تحصل عليه بالضبط، السعر ولماذا، الضمان، لمن لا يناسب.', 'اطلب الآن', 'معدل '.Catalog::label(Catalog::GOALS, $goal)],
            ['آخر فرصة', 'ينتهي الليلة — وسؤال أخير', 'تذكير قصير بالموعد، الاعتراض الأكثر شيوعاً وردّه، سؤال: ما الذي منعك؟', 'اطلب قبل الإغلاق', 'طلبات + ردود'],
        ];
        $table = "| # | اليوم | الرسالة | العنوان | الهيكل | الدعوة | المؤشر |\n|---|---|---|---|---|---|---|\n";
        foreach ($rows as $i => $r) {
            $table .= '| '.($i + 1).' | يوم '.$schedule[$i].' | '.implode(' | ', array_map(fn ($c) => str_replace('|', '/', $c), $r))." |\n";
        }
        $target = match ($goal) { 'sales' => '3٪ من المشتركين يشترون خلال '.$days.' يوماً', 'awareness' => '40٪ يفتحون 3 رسائل على الأقل', 'retention' => '15٪ من العملاء الحاليين يعودون خلال 30 يوماً', default => '10٪ من المشتركين يبدؤون محادثة خلال '.$days.' يوماً' };

        return ['emails_table' => rtrim($table), 'target_line' => $target, 'click_floor' => '5٪ من المستلمين', 'pain_short' => Str::limit($pain, 50, '…')];
    }

    private function utm(array $v): array
    {
        $url = trim((string) ($v['url'] ?? ''));
        if ($url !== '' && ! preg_match('~^https?://~i', $url)) {
            $url = 'https://'.$url;
        }
        $source = (string) ($v['channel'] ?? 'instagram');
        $medium = (string) ($v['medium'] ?? 'cpc');
        $campaign = $this->slugify((string) ($v['campaign'] ?? 'campaign')).'-'.now()->format('M-y');
        $campaign = Str::lower($campaign);
        $content = $this->slugify((string) ($v['content'] ?? ''));
        $build = function (string $c) use ($url, $source, $medium, $campaign): string {
            $params = ['utm_source' => $source, 'utm_medium' => $medium, 'utm_campaign' => $campaign];
            if ($c !== '') {
                $params['utm_content'] = $c;
            }
            $sep = str_contains($url, '?') ? '&' : '?';

            return ($url !== '' ? $url : 'https://example.com').$sep.http_build_query($params);
        };
        $variants = "| النسخة | utm_content | الرابط |\n|---|---|---|\n";
        foreach (['a', 'b', 'c'] as $letter) {
            $c = ($content !== '' ? $content : 'creative').'-'.$letter;
            $variants .= '| '.Str::upper($letter).' | '.$c.' | `'.$build($c)."` |\n";
        }

        return [
            'utm_source' => $source, 'utm_medium' => $medium, 'utm_campaign' => $campaign, 'campaign_slug' => $campaign,
            'utm_content_line' => $content !== '' ? $content : '(اختياري — يُملأ لكل إعلان)', 'utm_url' => $build($content), 'variants_table' => rtrim($variants),
        ];
    }

    private function landingReview(array $v): array
    {
        $headline = trim((string) ($v['headline'] ?? ''));
        $promise = trim((string) ($v['promise'] ?? ''));
        $proof = trim((string) ($v['proof'] ?? ''));
        $cta = trim((string) ($v['cta'] ?? ''));
        $fields = (int) ($v['fields_count'] ?? 0);
        $load = (string) ($v['load'] ?? 'medium');
        $criteria = [];
        // Each criterion: label, score out of its weight, finding, fix. Rules are declared, not inferred.
        $hasNumber = (bool) preg_match('/\d|[٠-٩]/u', $promise.' '.$headline);
        $criteria[] = ['وضوح الوعد في العنوان', $headline !== '' && mb_strlen($headline) <= 70 ? ($hasNumber ? 20 : 15) : 6, $headline === '' ? 'لا عنوان' : (mb_strlen($headline) > 70 ? 'العنوان أطول من 70 حرفاً' : ($hasNumber ? 'عنوان قصير بنتيجة محددة' : 'عنوان قصير لكن بلا نتيجة محددة')), $hasNumber ? 'أبقِه' : 'أضف نتيجة محددة أو مدة: «'.Str::limit($promise !== '' ? $promise : 'النتيجة خلال 7 أيام', 40, '…').'»', 20];
        $match = $promise !== '' && $headline !== '' && count(array_intersect(self::words($promise), self::words($headline))) > 0;
        $criteria[] = ['تطابق الوعد مع الإعلان', $match ? 15 : 7, $match ? 'الكلمات الأساسية مشتركة' : 'العنوان لا يكرر كلمات الوعد الذي جاء به الزائر', 'كرّر في العنوان الكلمات نفسها التي في الإعلان', 15];
        $proofScore = $proof === '' ? 0 : (preg_match('/\d|[٠-٩]/u', $proof) ? 20 : 12);
        $criteria[] = ['الدليل', $proofScore, $proof === '' ? 'لا دليل في الصفحة' : ($proofScore === 20 ? 'دليل برقم قابل للتحقق' : 'دليل عام بلا رقم'), $proof === '' ? 'أضف تقييمات حقيقية أو رقماً (عدد العملاء، نسبة الرضا) أو ضماناً' : 'أضف رقماً إلى الدليل وضع الدليل قبل الزر', 20];
        $ctaStrong = $cta !== '' && mb_strlen($cta) <= 25 && ! preg_match('/^(إرسال|أرسل|اضغط هنا|submit)$/u', $cta);
        $criteria[] = ['الخطوة التالية الواحدة', $cta === '' ? 0 : ($ctaStrong ? 15 : 8), $cta === '' ? 'لا زر واضح' : ($ctaStrong ? 'زر بفعل محدد' : 'نص الزر عام («'.$cta.'»)'), 'اجعل الزر يصف ما يحدث بعده: «اطلب العيّنة»، «احجز مكالمة 15 دقيقة»', 15];
        $criteria[] = ['طول النموذج', $fields <= 3 ? 15 : ($fields <= 5 ? 9 : 3), $fields.' حقول'.($fields <= 3 ? ' — مناسب' : ($fields <= 5 ? ' — متوسط' : ' — طويل')), $fields <= 3 ? 'أبقِه' : 'اختصر إلى 3 حقول (الاسم، الجوال، سؤال واحد) وانقل البقية إلى ما بعد الطلب', 15];
        $criteria[] = ['السرعة على الجوال', $load === 'fast' ? 15 : ($load === 'medium' ? 9 : 2), ['fast' => 'أقل من 3 ثوانٍ', 'medium' => '3–5 ثوانٍ', 'slow' => 'أكثر من 5 ثوانٍ'][$load] ?? '—', $load === 'fast' ? 'أبقِه' : 'اضغط الصور وأزل السكربتات غير الضرورية؛ كل ثانية تأخير تخفض التحويل نحو 7٪ (قاعدة إرشادية شائعة)', 15];
        $score = 0;
        $table = "| المعيار | الدرجة | ما وُجد | التصحيح |\n|---|---|---|---|\n";
        $fixes = [];
        foreach ($criteria as [$label, $pts, $finding, $fix, $max]) {
            $score += $pts;
            $table .= "| {$label} | {$pts}/{$max} | ".str_replace('|', '/', $finding).' | '.str_replace('|', '/', $fix)." |\n";
            if ($pts < $max) {
                $fixes[] = [$max - $pts, $label, $fix];
            }
        }
        usort($fixes, fn ($a, $b) => $b[0] <=> $a[0]);
        $fixList = '';
        foreach ($fixes as $i => [$gain, $label, $fix]) {
            $fixList .= ($i + 1).". **{$label}** (+{$gain} نقطة): {$fix}\n";
        }
        $url = trim((string) ($v['url'] ?? ''));

        return [
            'score' => $score, 'criteria_table' => rtrim($table), 'fixes_list' => $fixList !== '' ? rtrim($fixList) : 'لا تصحيحات مطلوبة — اختبر نسخة بديلة من العنوان فقط.',
            'url_line' => $url !== '' ? 'الصفحة: '.$url : 'الصفحة: (لم يُذكر رابط)',
            'top_risk' => $fixes !== [] ? 'الزائر يصل من الإعلان ويغادر قبل الزر بسبب «'.$fixes[0][1].'»' : 'الاكتفاء بنسخة واحدة بلا اختبار',
            'top_fix' => $fixes !== [] ? $fixes[0][2] : 'اختبر عنواناً بديلاً كل أسبوعين',
        ];
    }

    private function prompts(array $v): array
    {
        $task = (string) ($v['task'] ?? 'ad');
        $offer = (string) ($v['offer'] ?? '');
        $tasks = [
            'ad' => ['كتابة إعلان', "اكتب 3 نسخ إعلان لـ«{$offer}»: كل نسخة بعنوان ≤ 8 كلمات، نص ≤ 60 كلمة، ودعوة واحدة.", 'جدول من 3 صفوف: العنوان، النص، الدعوة.'],
            'angles' => ['أفكار محتوى', "اقترح 10 زوايا محتوى لـ«{$offer}» موزعة على مراحل الاكتشاف والتقييم والقرار.", 'قائمة مرقّمة: المرحلة، الزاوية، الصيغة، الدعوة.'],
            'objection' => ['الرد على اعتراض', "اكتب ردوداً على أكثر 3 اعتراضات محتملة على «{$offer}»، كل رد بدليل أو مثال، بلا وعود غير مثبتة.", 'لكل اعتراض: الاعتراض، الرد في 3 جمل، سؤال متابعة.'],
            'competitor' => ['تحليل منافس', "حلّل عرض المنافس الذي سأصفه مقابل «{$offer}»: الوعد، السعر، الدليل، الفجوات، وأين نتميّز فعلاً.", 'جدول مقارنة + 3 توصيات مرتبة بالأثر.'],
            'summary' => ['تلخيص', 'لخّص النص الذي سأرفقه في 5 نقاط قابلة للتنفيذ، وافصل الحقائق المسندة عن الآراء.', 'قسمان: حقائق (مع موضعها في النص)، وآراء/افتراضات.'],
        ];
        [$label, $instruction, $format] = $tasks[$task] ?? $tasks['ad'];
        $constraints = trim((string) ($v['constraints'] ?? ''));

        return [
            'task_label' => $label, 'task_instruction' => $instruction, 'task_format' => $format,
            'constraints_line' => $constraints !== '' ? $constraints : 'أقصى طول 120 كلمة.',
        ];
    }

    public static function firstNumber(string $text): int
    {
        $latin = strtr($text, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', ',' => '']);

        return preg_match('/\d+/', $latin, $m) ? (int) $m[0] : 0;
    }

    private static function words(string $text): array
    {
        return array_values(array_filter(preg_split('/[\s،,.:!؟?]+/u', mb_strtolower($text)) ?: [], fn ($w) => mb_strlen($w) >= 4));
    }

    private function slugify(string $text): string
    {
        $slug = Str::slug($text, '-');
        if ($slug === '') {
            $slug = 'campaign-'.substr(md5($text), 0, 6);
        }

        return $slug;
    }
}
