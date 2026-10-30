<?php

namespace Database\Seeders;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Assessment\Models\AssessmentRule;
use App\Domain\Assessment\Services\Catalog;
use Illuminate\Database\Seeder;

/**
 * Stage 4 completion (blueprint §4.2 + §8): routing rules that cover all twelve paths.
 * Idempotent: rules are keyed by audience × level × weakest dimension (updateOrCreate), lessons are matched by slug.
 * Owners' program skips SEO and automation (AcademySeeder::OWNER_ORDER), so owner rules route to social media / AI instead.
 */
class StageFourCompletionSeeder extends Seeder
{
    public function run(): void
    {
        $lessons = Lesson::query()->pluck('id', 'slug');
        foreach (['owner', 'marketer'] as $audience) {
            foreach (array_keys(Catalog::LEVELS) as $level) {
                foreach (array_keys(Catalog::DIMENSIONS) as $dimension) {
                    [$start, $priorities] = $this->rule($audience, $level, $dimension);
                    AssessmentRule::query()->updateOrCreate(
                        ['audience' => $audience, 'level' => $level, 'weakest_dimension' => $dimension],
                        ['lesson_id' => $lessons[$start] ?? null, 'priorities' => $priorities],
                    );
                }
            }
        }
    }

    /** @return array{0:string,1:array<int,array<string,string>>} [start lesson slug, three priorities (now / this month / after)] */
    public function rule(string $audience, string $level, string $dimension): array
    {
        $owner = $audience === 'owner';
        $you = $owner ? 'مشروعك' : 'مشروع عميلك';
        // Owners' program has no SEO or automation path; they get the closest owner-friendly course instead.
        $seo = $owner ? 'social-media' : 'seo-content-marketing';
        $automation = $owner ? 'ai-in-marketing' : 'automation-workflows';
        $l = fn (string $slug, string $title, string $reason) => ['title' => $title, 'reason' => $reason, 'lesson' => $slug];
        $c = fn (string $slug, string $title, string $reason) => ['title' => $title, 'reason' => $reason, 'course' => $slug];

        $map = [
            'goal' => [
                'beginner' => ['what-marketing-really-is', [
                    $l('what-marketing-really-is', 'ثبّت رقماً واحداً وتاريخاً لـ {{offer}}', "كل قرار تالٍ في {$you} (الشريحة، الرسالة، القناة) يُقاس بهذا الرقم؛ بدونه لا تعرف إن كان التسويق ينفع."),
                    $l('first-channel-and-budget', 'حوّل الرقم إلى خطة قناة واحدة بميزانية {{budget}}', 'الرقم يصبح قابلاً للتنفيذ عندما يُترجم إلى تكلفة طلب مقبولة وقاعدة إيقاف مكتوبة.'),
                    $c('offer-value-positioning', 'اربط الرقم بعرض قيمة مكتوب', 'الهدف الرقمي بلا عرض واضح يُجبرك على خصومات؛ مسار العرض والقيمة يثبّت لماذا يدفع العميل.'),
                ]],
                'intermediate' => ['offer-job-and-alternative', [
                    $l('offer-job-and-alternative', 'حدّد المهمة التي يشتريها عميلك من {{offer}} وبديله الحالي', 'عندما يكون الهدف مكتوباً لكن الرقم لا يتحرك، فالمشكلة غالباً في قيمة العرض مقابل البديل لا في الكمية.'),
                    $c('measurement-analytics-cro', 'ابنِ شجرة قرار القياس حول {{goal_number}}', 'المستوى المتوسط يحتاج مؤشرات قائدة تُنبئ بالرقم قبل نهاية الشهر، لا تقارير بعد فوات القرار.'),
                    $c('integrated-marketing-plan', 'اجمع الهدف والعرض والقناة في خطة ربع سنة', 'الخطة المتكاملة تمنع قفز الأهداف كل أسبوع وتربط الميزانية بالمؤشرات.'),
                ]],
                'advanced' => ['integrated-evidence-brief', [
                    $l('integrated-evidence-brief', 'اكتب موجز الأدلة لربع السنة القادم', 'مستواك متقدم؛ الفجوة الآن في اتساق الأهداف عبر القنوات لا في تعريف الرقم نفسه.'),
                    $c('measurement-analytics-cro', 'راجع نموذج الإسناد ولوحة القياس', 'الأهداف المتقدمة تنهار إن قاست كل قناة نفسها بنفسها؛ القياس الموحد هو ما يحمي الرقم.'),
                    $c($automation, 'أتمت المتابعة لتثبيت النتائج', 'بعد ثبات الهدف والقياس، الأتمتة تحوّل التحسينات من جهد شخصي إلى نظام يعمل كل أسبوع.'),
                ]],
            ],
            'audience' => [
                'beginner' => ['first-segment', [
                    $l('first-segment', 'اختر الشريحة الأولى لـ {{offer}}', 'الرسالة والقناة والسعر كلها تُبنى على شريحة واحدة واضحة؛ الاستهداف العام يُشتت الميزانية.'),
                    $l('market-map', 'ارسم خريطة السوق حول هذه الشريحة', 'معرفة بديل الشريحة الحالي والفجوة التي لا يغطيها المنافسون تعطيك تموضعاً بدل منافسة سعرية.'),
                    $c('audience-and-personas', 'وثّق شخصية العميل ببيانات حقيقية', 'بعد الشريحة الأولى، مسار الجمهور يحوّل الانطباعات إلى شخصية مبنية على مقابلات وأرقام.'),
                ]],
                'intermediate' => ['audience-research-question', [
                    $l('audience-research-question', 'اكتب سؤال البحث الذي تجيب عنه مقابلاتك القادمة', 'الشريحة موجودة لكنها مبنية على افتراضات؛ سؤال بحث واحد يحدد ما تحتاج سماعه من العملاء فعلاً.'),
                    $c('content-writing-persuasion', 'حوّل لغة العملاء إلى نصوص إقناع', 'كلمات العملاء أنفسهم في المقابلات هي أقوى مادة للعناوين والاعتراضات.'),
                    $c('email-and-crm', 'قسّم جمهورك في CRM بمرحلة العلاقة', 'الجمهور الواضح يستحق رسائل مختلفة لمن لم يشترِ ومن اشترى مرة ومن تكرر.'),
                ]],
                'advanced' => ['email-consent-expectations', [
                    $l('email-consent-expectations', 'اضبط الموافقة والتوقعات لكل شريحة في {{channel}} والبريد', 'بمستوى متقدم، نمو الجمهور يأتي من الاحتفاظ والتقسيم الدقيق لا من اكتشاف شرائح جديدة فقط.'),
                    $c($automation, 'أتمت التأهيل والمتابعة لكل شريحة', 'التقسيم الدقيق بلا أتمتة يتحول إلى عمل يدوي لا يُستدام.'),
                    $c('measurement-analytics-cro', 'قِس قيمة كل شريحة (LTV وCAC) بشكل منفصل', 'الشريحة التي تبدو كبيرة قد تكون الأقل ربحية؛ القياس بالشريحة يصحح توزيع الميزانية.'),
                ]],
            ],
            'offer' => [
                'beginner' => ['core-message', [
                    $l('core-message', 'اكتب رسالة أساسية واحدة لـ {{offer}}', 'الزيارات بلا طلبات غالباً مشكلة رسالة: القارئ لا يفهم لماذا أنت ولماذا الآن خلال ثلاث ثوانٍ.'),
                    $l('first-segment', 'تحقق أن الرسالة تخاطب شريحة واحدة', 'الرسالة التي تحاول إقناع الجميع لا تقنع أحداً؛ راجع ملف الشريحة الأولى.'),
                    $c('offer-value-positioning', 'اختبر عرض القيمة والتموضع مقابل البديل', 'بعد الرسالة الأولى، مسار العرض يختبر الوعد والدليل والسعر مقابل ما يفعله العميل اليوم.'),
                ]],
                'intermediate' => ['offer-job-and-alternative', [
                    $l('offer-job-and-alternative', 'حدّد مهمة العميل وبديله قبل تعديل {{offer}}', 'العرض الذي يُعاد صياغته كل شهر يفتقد تعريفاً للمهمة التي يُستأجر لأدائها.'),
                    $c('content-writing-persuasion', 'اكتب ثلاثة نصوص إقناع تجيب عن الاعتراضات', 'العرض الواضح يحتاج نصوصاً تثبت الوعد بدليل وشرط ودعوة واحدة.'),
                    $c($seo, 'انشر العرض حيث يبحث العميل عنه', 'الرسالة الجيدة لا تُقرأ إن لم تكن في المكان الذي يبحث فيه العميل عن الحل.'),
                ]],
                'advanced' => ['copy-editorial-brief', [
                    $l('copy-editorial-brief', 'اكتب موجزاً تحريرياً يثبّت الوعد والدليل لكل قطعة', 'بمستوى متقدم، ضعف العرض يظهر كتشتت في النصوص عبر القنوات لا كغياب للرسالة.'),
                    $c('ai-in-marketing', 'ولّد بدائل الرسالة بالذكاء الاصطناعي ضمن حدود الموجز', 'البدائل السريعة مفيدة فقط عندما يحكمها موجز وحزمة مصادر؛ وإلا تعود الرسالة إلى العموميات.'),
                    $c('paid-ads', 'اختبر الرسائل بتجربة مدفوعة محدودة بقواعد توقف', 'الإعلان المدفوع أسرع طريقة لمعرفة أي وعد يحوّل فعلاً، بشرط حد تكلفة وقواعد إيقاف.'),
                ]],
            ],
            'channel' => [
                'beginner' => ['first-channel-and-budget', [
                    $l('first-channel-and-budget', 'ركّز {{budget}} على قناة واحدة بقاعدة إيقاف', 'توزيع الميزانية على قنوات عدة يُخفي ما ينفع؛ قناة واحدة بتكلفة طلب مستهدفة تعطيك جواباً خلال أسبوعين.'),
                    $l('core-message', 'تأكد أن الرسالة جاهزة قبل الإنفاق', 'أغلب هدر الإعلانات سببه رسالة غير واضحة لا قناة خاطئة.'),
                    $c('social-media', 'ابنِ تقويم محتوى شهري لقناتك', 'القناة العضوية تحتاج إيقاعاً وزوايا محددة، لا منشورات عندما يتوفر الوقت.'),
                ]],
                'intermediate' => [$owner ? 'social-channel-role' : 'seo-query-intent-map', [
                    $owner
                        ? $l('social-channel-role', 'حدّد دور {{channel}} في رحلة العميل قبل التقويم', 'القناة التي تعمل بلا دور محدد (اكتشاف أم ثقة أم تحويل) تُقاس بالمقياس الخطأ.')
                        : $l('seo-query-intent-map', 'ارسم خريطة نيات البحث لـ {{offer}}', 'القناة التي تبني طلباً مستمراً هي التي تجيب عن أسئلة العميل وقت بحثه، لا وقت نشرك.'),
                    $c('paid-ads', 'أضف تجربة مدفوعة بحد تكلفة طلب', 'بعد قناة عضوية مستقرة، الإعلان المدفوع يوسّع الوصول بشرط اقتصاد طلب مكتوب مسبقاً.'),
                    $c('email-and-crm', 'حوّل زوار القناة إلى قائمة تملكها', 'كل قناة مستأجرة؛ البريد وCRM هما القناة الوحيدة التي تملكها فعلاً.'),
                ]],
                'advanced' => ['ads-objective-economics', [
                    $l('ads-objective-economics', 'اشتق حد تكلفة الطلب من هامش {{offer}} قبل التوسع', 'بمستوى متقدم، الخطر ليس في اختيار القناة بل في التوسع فيها بأرقام لا تحتمل الهامش.'),
                    $c($seo, 'وازن المدفوع بقناة عضوية تراكمية', 'الاعتماد على قناة مدفوعة واحدة يجعل التكلفة رهينة المزاد؛ المحتوى يخفض التكلفة المختلطة مع الوقت.'),
                    $c($automation, 'أتمت المتابعة بين القنوات', 'التنقل بين قنوات متعددة يضيع الطلبات في الفجوات؛ سير العمل المؤتمت يحفظها.'),
                ]],
            ],
            'measure' => [
                'beginner' => ['first-marketing-plan', [
                    $l('first-marketing-plan', 'ابنِ لوحة قياس أسبوعية من مؤشرين', 'الهدف الرقمي بلا متابعة أسبوعية يعود «إحساساً» خلال شهر.'),
                    $l('first-channel-and-budget', 'اربط المؤشر بقاعدة إيقاف للقناة', 'المؤشر الذي لا يقود إلى قرار (استمر/عدّل/توقف) مجرد رقم على لوحة.'),
                    $c('measurement-analytics-cro', 'تعلّم قراءة الأرقام وتحسين التحويل', 'بعد اللوحة الأولى، مسار القياس يعلّمك متى يكون الفرق حقيقياً ومتى هو ضجيج.'),
                ]],
                'intermediate' => ['measurement-decision-tree', [
                    $l('measurement-decision-tree', 'ارسم شجرة قرار القياس لـ {{goal_number}}', 'لوحتك تعرض أرقاماً لكنها لا تقول ماذا تفعل غداً؛ شجرة القرار تربط كل مؤشر بفعل.'),
                    $c('email-and-crm', 'قِس الاحتفاظ لا الاكتساب فقط', 'معدل العودة والشراء المتكرر هما ما يحدد إن كانت تكلفة الاكتساب مقبولة.'),
                    $c('ai-in-marketing', 'استخدم الذكاء الاصطناعي لتفسير الأرقام ضمن حدود معلنة', 'التحليل المساعد يختصر الوقت، لكن بحزمة بيانات ومصادر محددة لا يختلق منها.'),
                ]],
                'advanced' => [$owner ? 'ai-task-boundaries' : 'automation-process-map', [
                    $owner
                        ? $l('ai-task-boundaries', 'حدّد المهام التحليلية التي تفوّضها للذكاء الاصطناعي وحدودها', 'بمستوى متقدم، القياس يستهلك وقتك؛ تفويض محسوب بحدود واضحة يحرر وقتك للقرار.')
                        : $l('automation-process-map', 'ارسم خريطة العملية قبل أتمتة التقارير', 'بمستوى متقدم، القياس اليدوي هو عنق الزجاجة؛ خريطة العملية تكشف ما يستحق الأتمتة.'),
                    $c('measurement-analytics-cro', 'راجع خطة اختبارات التحويل ونموذج الإسناد', 'التحسين المستمر يحتاج اختبارات بحجم عينة محسوب لا تغييرات بالحدس.'),
                    $c('integrated-marketing-plan', 'اربط لوحة القياس بخطة ربع السنة', 'المؤشرات التي لا تغذّي الخطة والميزانية تبقى تقارير تُقرأ ولا تُستخدم.'),
                ]],
            ],
        ];

        return $map[$dimension][$level];
    }
}
