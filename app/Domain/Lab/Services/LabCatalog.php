<?php

namespace App\Domain\Lab\Services;

use App\Domain\Assessment\Services\Catalog;

/**
 * The fifteen tools of the lab (blueprint §4.6, §8 stage 2): ten generators with base templates (no AI needed),
 * four calculators and one ad-campaign simulator. This is the single source the seeder, the engine and the studio read.
 *
 * Field definition: key, label, type (text|textarea|number|select), options (channels|goals|sectors|inline map),
 * source (project profile key that pre-fills it), from_asset ([type, key] of an earlier asset that pre-fills it),
 * required, placeholder, hint, default.
 */
class LabCatalog
{
    /** The asset chain in plan order: «خطة مشروعك تتجمّع» is complete when a project holds one of each. */
    public const CHAIN = [
        'customer_persona' => 'شخصية العميل',
        'value_proposition' => 'عرض القيمة',
        'campaign_brief' => 'موجز الحملة',
        'content_angles' => 'زوايا المحتوى',
        'headlines_cta' => 'عناوين وCTA',
        'content_calendar' => 'تقويم المحتوى',
        'email_sequence' => 'تسلسل البريد',
        'utm_links' => 'روابط UTM',
        'landing_review' => 'مراجعة صفحة الهبوط',
        'prompt_pack' => 'حزمة Prompts',
    ];

    /** Lesson assets (stage 1) that tools can also feed from, with their labels. */
    public const LESSON_ASSETS = [
        'value_card' => 'بطاقة القيمة', 'market_map' => 'خريطة السوق', 'segment_profile' => 'ملف الشريحة الأولى',
        'core_message' => 'الرسالة الأساسية', 'channel_plan' => 'خطة القناة الأولى', 'marketing_plan' => 'خطة التسويق الأولى',
    ];

    public const CALCULATORS = ['cac' => 'تكلفة اكتساب العميل (CAC)', 'ltv' => 'القيمة الدائمة للعميل (LTV)', 'roas' => 'العائد على الإنفاق الإعلاني (ROAS)', 'conversion-sample' => 'معدل التحويل وحجم العينة'];

    public static function label(string $type): string
    {
        return self::CHAIN[$type] ?? self::LESSON_ASSETS[$type] ?? ($type === 'profile' ? 'ملف المشروع' : $type);
    }

    /** @return array<int, array<string, mixed>> */
    public static function tools(): array
    {
        $tools = [];
        $position = 1;
        foreach (self::generators() as $slug => $def) {
            $tools[] = $def + ['slug' => $slug, 'kind' => 'generator', 'position' => $position++, 'is_published' => true];
        }
        foreach (self::calculators() as $slug => $def) {
            $tools[] = $def + ['slug' => $slug, 'kind' => 'calculator', 'position' => $position++, 'is_published' => true, 'credit_cost' => 0, 'output_type' => null, 'feeds_from' => [], 'feeds_into' => [], 'quality_rules' => [], 'output_template' => null];
        }
        $tools[] = self::simulator() + ['slug' => 'ad-campaign-simulator', 'kind' => 'simulator', 'position' => $position, 'is_published' => true, 'credit_cost' => 0, 'output_type' => null, 'feeds_from' => [], 'feeds_into' => [], 'quality_rules' => [], 'output_template' => null];

        return $tools;
    }

    public static function find(string $slug): ?array
    {
        foreach (self::tools() as $tool) {
            if ($tool['slug'] === $slug) {
                return $tool;
            }
        }

        return null;
    }

    private static function f(string $key, string $label, array $extra = []): array
    {
        return $extra + ['key' => $key, 'label' => $label, 'type' => 'text', 'required' => true];
    }

    /** @return array<string, array<string, mixed>> */
    public static function generators(): array
    {
        return [
            'customer-persona' => [
                'name' => 'شخصية العميل', 'category' => 'الجمهور', 'credit_cost' => 2, 'output_type' => 'customer_persona', 'course_slug' => 'audience-and-personas',
                'description' => 'حوّل وصفاً عاماً للجمهور إلى شخصية واحدة محددة: من هي، ما مشكلتها، ما الذي يدفعها للبحث الآن، وأين تجدها.',
                'tagline' => 'تُدخل: العرض والجمهور والمشكلة والقناة → تخرج: بطاقة شخصية عميل من صفحة',
                'feeds_from' => ['profile', 'segment_profile'], 'feeds_into' => ['value_proposition', 'campaign_brief', 'content_angles'],
                'quality_rules' => ['required' => ['single_audience', 'single_channel'], 'advisory' => ['risk_mitigation']],
                'input_schema' => [
                    self::f('offer', 'ما الذي تبيعه؟', ['source' => 'offer', 'placeholder' => 'مثال: منتجات عناية طبيعية للبشرة الحساسة']),
                    self::f('sector', 'القطاع', ['type' => 'select', 'options' => 'sectors', 'source' => 'sector']),
                    self::f('audience', 'من هي الشخصية؟ (جملة واحدة لشريحة واحدة)', ['source' => 'audience', 'from_asset' => ['type' => 'segment_profile', 'key' => 'who'], 'placeholder' => 'مثال: نساء 25–40 في الرياض ذوات بشرة حساسة']),
                    self::f('pain', 'المشكلة الأساسية التي تعيشها', ['type' => 'textarea', 'placeholder' => 'مثال: جرّبت منتجات كثيرة سبّبت تهيّجاً ولا تثق بالادعاءات']),
                    self::f('trigger', 'ما الذي يدفعها للبحث عن حل الآن؟', ['placeholder' => 'مثال: مناسبة قريبة أو تفاقم المشكلة']),
                    self::f('channel', 'أين تبحث عن الحل؟ (قناة واحدة)', ['type' => 'select', 'options' => 'channels', 'source' => 'channel']),
                    self::f('objection', 'أكبر اعتراض يمنعها من الشراء', ['placeholder' => 'مثال: السعر أعلى من البدائل', 'required' => false]),
                ],
                'example_input' => ['offer' => 'منتجات عناية طبيعية للبشرة الحساسة', 'sector' => 'beauty', 'audience' => 'نساء 25–40 في الرياض ذوات بشرة حساسة', 'pain' => 'جرّبت منتجات كثيرة سبّبت تهيّجاً ولم تجد ما تثق به', 'trigger' => 'تفاقم التهيّج قبل مناسبة عائلية قريبة', 'channel' => 'instagram', 'objection' => 'السعر أعلى من البدائل في الصيدلية'],
                'output_template' => <<<'MD'
# شخصية العميل — {{persona_name}}

| | |
|---|---|
| الشريحة | **{{audience}}** |
| القطاع | {{sector_label}} |
| ما تبحث عنه | {{offer}} |
| أين تبحث | {{channel_label}} |

## ما تعيشه الآن
- **المشكلة**: {{pain}}
- **المحفّز للبحث الآن**: {{trigger}}
- **الاعتراض الأكبر**: {{objection_line}}

## ما الذي يقنعها
- دليل ملموس يرد على «{{objection_short}}» قبل أن تسأل.
- لغة تصف مشكلتها بكلماتها هي، لا بمواصفات المنتج.
- خطوة أولى صغيرة ومنخفضة المخاطر (عيّنة، استشارة، ضمان).

## أين ومتى نصلها
- القناة الأولى: **{{channel_label}}** (قناة واحدة حتى تُعرف تكلفة الطلب).
- التوقيت: عند لحظة المحفّز ({{trigger}}) لا قبلها.

## الخطر وحلّه
- **الخطر**: أن تتسع الشريحة إلى «كل من يهمه {{offer}}» فتضعف الرسالة. **الحل**: اختبر مع هذه الشخصية وحدها 14 يوماً قبل أي توسيع.

_أُنشئت في مختبر أثر — {{date}}_
MD,
            ],

            'value-proposition' => [
                'name' => 'عرض القيمة', 'category' => 'التخطيط', 'credit_cost' => 2, 'output_type' => 'value_proposition', 'course_slug' => 'offer-value-positioning',
                'description' => 'صِغ وعداً واحداً واضحاً: لمن، ما النتيجة، لماذا أنت لا البديل، وما الدليل. بثلاث صياغات جاهزة للاستخدام.',
                'tagline' => 'تُدخل: الشخصية ومشكلتها وعرضك وما يميزك ودليلك → تخرج: عرض قيمة بثلاث صياغات واختبار وضوح',
                'feeds_from' => ['profile', 'customer_persona', 'value_card'], 'feeds_into' => ['campaign_brief', 'headlines_cta', 'landing_review'],
                'quality_rules' => ['required' => ['single_audience'], 'advisory' => ['risk_mitigation']],
                'input_schema' => [
                    self::f('audience', 'لمن؟ (شريحة واحدة)', ['source' => 'audience', 'from_asset' => ['type' => 'customer_persona', 'key' => 'audience'], 'placeholder' => 'مثال: نساء 25–40 ذوات بشرة حساسة']),
                    self::f('pain', 'المشكلة التي تحلّها', ['type' => 'textarea', 'from_asset' => ['type' => 'customer_persona', 'key' => 'pain'], 'placeholder' => 'مثال: التهيّج من المنتجات المعطّرة']),
                    self::f('offer', 'عرضك', ['source' => 'offer', 'placeholder' => 'مثال: سيروم طبيعي بلا عطور']),
                    self::f('differentiator', 'ما الذي يميزك عن البديل؟', ['placeholder' => 'مثال: تركيبة بلا عطور مختبَرة على بشرة حساسة']),
                    self::f('proof', 'دليل واحد يمكن التحقق منه', ['placeholder' => 'مثال: 120 تقييماً بمتوسط 4.8 أو شهادة مختبر']),
                    self::f('alternative', 'ما الذي تستخدمه الشريحة الآن بدلاً منك؟', ['placeholder' => 'مثال: كريمات الصيدلية العامة', 'required' => false]),
                ],
                'example_input' => ['audience' => 'نساء 25–40 في الرياض ذوات بشرة حساسة', 'pain' => 'التهيّج المتكرر من المنتجات المعطّرة', 'offer' => 'سيروم طبيعي بلا عطور', 'differentiator' => 'تركيبة بلا عطور مختبَرة على بشرة حساسة', 'proof' => 'شهادة اختبار جلدي و120 تقييماً بمتوسط 4.8', 'alternative' => 'كريمات الصيدلية العامة'],
                'output_template' => <<<'MD'
# عرض القيمة — {{offer}}

## الصياغة الأساسية
**لـ{{audience}}** الذين يعانون من {{pain}}، **{{offer}}** يقدّم {{differentiator}}. بخلاف {{alternative_line}}، الدليل: {{proof}}.

## ثلاث صياغات جاهزة
| الاستخدام | الصياغة |
|---|---|
| عنوان صفحة الهبوط | {{offer}}: {{differentiator}} — لـ{{audience}} |
| سطر في إعلان | تعبت من {{pain_short}}؟ {{offer}} — {{proof_short}} |
| رد على واتساب | نساعد {{audience}} على التخلص من {{pain_short}} عبر {{offer}}. {{proof}}. |

## اختبار الوضوح (أجب قبل النشر)
- هل يفهم غريب في 5 ثوانٍ لمن هذا؟ **{{audience}}** → نعم إن بقيت شريحة واحدة.
- هل الدليل قابل للتحقق؟ **{{proof}}**
- هل يذكر البديل ولماذا أنت أفضل؟ **{{alternative_line}}**

## الخطر وحلّه
- **الخطر**: وعد أوسع من الدليل. **الحل**: لا تدّعِ إلا ما يسنده «{{proof_short}}»، وأضف دليلاً ثانياً بعد أول 10 عملاء.

_أُنشئ في مختبر أثر — {{date}}_
MD,
            ],

            'campaign-brief-builder' => [
                'name' => 'موجز الحملة', 'category' => 'التخطيط', 'credit_cost' => 0, 'output_type' => 'campaign_brief', 'course_slug' => 'marketing-foundations',
                'description' => 'حوّل الهدف والجمهور والعرض والميزانية إلى موجز حملة من صفحة واحدة: هدف برقم وتاريخ، قناة واحدة، مقياس وقاعدة إيقاف.',
                'tagline' => 'تُدخل: الهدف والجمهور والعرض والقناة والميزانية → تخرج: موجز حملة جاهز للتنفيذ خلال 30 يوماً',
                'feeds_from' => ['profile', 'customer_persona', 'value_proposition', 'core_message'], 'feeds_into' => ['content_angles', 'headlines_cta', 'utm_links', 'content_calendar'],
                'quality_rules' => ['required' => ['goal_number_date', 'single_audience', 'metric_stop_rule', 'single_channel', 'risk_mitigation', 'budget_consistency'], 'advisory' => []],
                'input_schema' => [
                    self::f('goal', 'الهدف برقم وتاريخ', ['source' => 'goal_sentence', 'placeholder' => 'مثال: 60 طلباً مؤهلاً خلال 30 يوماً']),
                    self::f('audience', 'الجمهور (شريحة واحدة)', ['source' => 'audience', 'from_asset' => ['type' => 'customer_persona', 'key' => 'audience'], 'placeholder' => 'مثال: نساء 25–40 ذوات بشرة حساسة في الرياض']),
                    self::f('offer', 'العرض', ['type' => 'textarea', 'source' => 'offer', 'from_asset' => ['type' => 'value_proposition', 'key' => 'offer'], 'placeholder' => 'مثال: سيروم طبيعي بلا عطور مع ضمان استرجاع']),
                    self::f('channel', 'القناة (واحدة)', ['type' => 'select', 'options' => 'channels', 'source' => 'channel']),
                    self::f('budget', 'الميزانية الشهرية (ريال)', ['type' => 'number', 'source' => 'budget', 'placeholder' => '3000']),
                    self::f('risk', 'أكبر خطر تتوقعه', ['placeholder' => 'مثال: تكلفة الطلب أعلى من 50 ريالاً', 'required' => false]),
                ],
                'example_input' => ['goal' => '60 طلباً مؤهلاً خلال 30 يوماً', 'audience' => 'نساء 25–40 ذوات بشرة حساسة في الرياض', 'offer' => 'سيروم طبيعي بلا عطور مع ضمان استرجاع 14 يوماً', 'channel' => 'instagram', 'budget' => 3000, 'risk' => 'تكلفة الطلب تتجاوز 50 ريالاً'],
                'output_template' => <<<'MD'
# موجز الحملة — {{offer_short}}

| | |
|---|---|
| الهدف | **{{goal}}** |
| الجمهور | {{audience}} |
| العرض | {{offer}} |
| القناة الواحدة | {{channel_label}} |
| الميزانية | {{budget_fmt}} ريال شهرياً ({{daily_budget}} ريال يومياً) |
| الرسالة | نساعد {{audience}} على {{goal_short}} من خلال {{offer_short}}. |

## الحساب قبل الإنفاق
- تكلفة الطلب المرجعية للقناة: **{{cpl}} ريال** (افتراض مُعلن للمشاريع الصغيرة في السوق السعودي، عدّله بأرقامك بعد 14 يوماً).
- الطلبات المتوقعة = الميزانية ÷ التكلفة المرجعية = **{{expected_leads}} طلباً** شهرياً.
- {{budget_verdict}}

## المقياس وقاعدة الإيقاف
- المؤشر القائد: تكلفة الطلب المؤهل. مؤشر النتيجة: عدد الطلبات المؤهلة.
- قاعدة الإيقاف: إذا تجاوزت تكلفة الطلب **{{stop_cpl}} ريالاً** بعد 14 يوماً من الإنفاق المستمر، أوقف وعدّل الرسالة أو الشريحة.
- قاعدة المضاعفة: إذا كانت التكلفة أقل من {{cpl}} ريالاً، ارفع الميزانية 25٪ أسبوعياً.

## الخطر وحلّه
- **الخطر**: {{risk_line}}
- **الحل**: {{risk_fix}}

## التجربة الأولى
اختبر رسالة واحدة على شريحة واحدة لمدة 14 يوماً قبل توسيع الميزانية. راجع الادعاءات والأرقام وصوت العلامة قبل الإطلاق.

_أُنشئ في مختبر أثر — {{date}}_
MD,
            ],

            'content-angle-generator' => [
                'name' => 'مولّد زوايا المحتوى', 'category' => 'المحتوى', 'credit_cost' => 2, 'output_type' => 'content_angles', 'course_slug' => 'content-writing-persuasion',
                'description' => 'استخرج ثماني زوايا محتوى مرتبطة بمرحلة العميل (اكتشاف، تقييم، قرار) مع خطّاف وصيغة ودعوة لكل زاوية.',
                'tagline' => 'تُدخل: العرض والجمهور والهدف والمشكلة والاعتراض → تخرج: 8 زوايا محتوى موزعة على رحلة العميل',
                'feeds_from' => ['profile', 'customer_persona', 'campaign_brief'], 'feeds_into' => ['content_calendar', 'headlines_cta'],
                'quality_rules' => ['required' => ['single_audience'], 'advisory' => ['metric_stop_rule']],
                'input_schema' => [
                    self::f('offer', 'العرض', ['source' => 'offer', 'from_asset' => ['type' => 'campaign_brief', 'key' => 'offer']]),
                    self::f('audience', 'الجمهور (شريحة واحدة)', ['source' => 'audience', 'from_asset' => ['type' => 'customer_persona', 'key' => 'audience']]),
                    self::f('goal', 'هدف المحتوى', ['type' => 'select', 'options' => 'goals', 'source' => 'goal']),
                    self::f('pain', 'المشكلة التي يعيشها الجمهور', ['type' => 'textarea', 'from_asset' => ['type' => 'customer_persona', 'key' => 'pain']]),
                    self::f('objection', 'الاعتراض الأكثر تكراراً', ['from_asset' => ['type' => 'customer_persona', 'key' => 'objection'], 'placeholder' => 'مثال: السعر، أو «هل يناسب بشرتي؟»']),
                ],
                'example_input' => ['offer' => 'سيروم طبيعي بلا عطور', 'audience' => 'نساء 25–40 ذوات بشرة حساسة', 'goal' => 'leads', 'pain' => 'التهيّج المتكرر من المنتجات المعطّرة', 'objection' => 'هل يناسب بشرتي فعلاً؟'],
                'output_template' => <<<'MD'
# زوايا المحتوى — {{offer}}

لـ**{{audience}}** · الهدف: {{goal_label}} · المشكلة: {{pain}}

{{angles_table}}

## كيف تستخدمها
- زاوية واحدة لكل منشور؛ لا تخلط مرحلتين في منشور.
- قِس كل زاوية بمؤشر واحد (حفظ/مشاركة للاكتشاف، تعليق/رسالة للتقييم، نقرة/طلب للقرار) وأوقف ما لا يحقق الحد بعد 3 منشورات.
- الاعتراض «{{objection}}» له زاويتان أعلاه؛ اجعلهما أول ما تنشر.

_أُنشئت في مختبر أثر — {{date}}_
MD,
            ],

            'headlines-cta' => [
                'name' => 'عناوين وCTA', 'category' => 'المحتوى', 'credit_cost' => 2, 'output_type' => 'headlines_cta', 'course_slug' => 'content-writing-persuasion',
                'description' => 'عشرة عناوين بصيغ مجرّبة (نتيجة، سؤال، رقم، تحذير، كيف…) وخمس دعوات لاتخاذ إجراء مناسبة للقناة.',
                'tagline' => 'تُدخل: العرض والجمهور والنتيجة الأهم والاعتراض والقناة → تخرج: 10 عناوين و5 دعوات مع متى تستخدم كلاً منها',
                'feeds_from' => ['profile', 'value_proposition', 'content_angles'], 'feeds_into' => ['content_calendar', 'landing_review', 'email_sequence'],
                'quality_rules' => ['required' => ['single_audience', 'single_channel'], 'advisory' => []],
                'input_schema' => [
                    self::f('offer', 'العرض', ['source' => 'offer', 'from_asset' => ['type' => 'value_proposition', 'key' => 'offer']]),
                    self::f('audience', 'الجمهور (شريحة واحدة)', ['source' => 'audience', 'from_asset' => ['type' => 'value_proposition', 'key' => 'audience']]),
                    self::f('benefit', 'النتيجة الأهم للعميل', ['from_asset' => ['type' => 'value_proposition', 'key' => 'differentiator'], 'placeholder' => 'مثال: بشرة هادئة بلا تهيّج خلال 7 أيام']),
                    self::f('objection', 'الاعتراض الذي يجب تجاوزه', ['placeholder' => 'مثال: السعر', 'required' => false]),
                    self::f('channel', 'القناة', ['type' => 'select', 'options' => 'channels', 'source' => 'channel']),
                ],
                'example_input' => ['offer' => 'سيروم طبيعي بلا عطور', 'audience' => 'نساء ذوات بشرة حساسة', 'benefit' => 'بشرة هادئة بلا تهيّج خلال 7 أيام', 'objection' => 'السعر أعلى من البدائل', 'channel' => 'instagram'],
                'output_template' => <<<'MD'
# عناوين وCTA — {{offer}}

لـ**{{audience}}** على {{channel_label}} · النتيجة: {{benefit}}

## عشرة عناوين
{{headlines_list}}

## خمس دعوات لاتخاذ إجراء
{{cta_list}}

## قاعدة الاختبار
اختبر عنوانين فقط في الأسبوع الأول على الشريحة نفسها، وأبقِ الفائز بمعدل نقر أعلى بـ20٪ على الأقل. أوقف أي عنوان يعد بما لا يسنده دليلك.

_أُنشئت في مختبر أثر — {{date}}_
MD,
            ],

            'content-calendar' => [
                'name' => 'تقويم المحتوى', 'category' => 'المحتوى', 'credit_cost' => 2, 'output_type' => 'content_calendar', 'course_slug' => 'social-media',
                'description' => 'تقويم أسبوعي لقناة واحدة يوزّع ركائز المحتوى (تعليمي، دليل، خلف الكواليس، عرض، تفاعل) مع دعوة لكل منشور ومؤشر قياس.',
                'tagline' => 'تُدخل: العرض والجمهور والقناة وعدد الأسابيع والمنشورات → تخرج: جدول منشورات بالتاريخ والركيزة والدعوة والمؤشر',
                'feeds_from' => ['profile', 'content_angles', 'campaign_brief'], 'feeds_into' => ['utm_links'],
                'quality_rules' => ['required' => ['goal_number_date', 'single_channel', 'metric_stop_rule'], 'advisory' => ['single_audience']],
                'input_schema' => [
                    self::f('offer', 'العرض', ['source' => 'offer', 'from_asset' => ['type' => 'campaign_brief', 'key' => 'offer']]),
                    self::f('audience', 'الجمهور', ['source' => 'audience', 'from_asset' => ['type' => 'customer_persona', 'key' => 'audience']]),
                    self::f('channel', 'القناة (واحدة)', ['type' => 'select', 'options' => 'channels', 'source' => 'channel']),
                    self::f('weeks', 'عدد الأسابيع', ['type' => 'select', 'options' => ['2' => 'أسبوعان', '4' => '4 أسابيع'], 'default' => '4']),
                    self::f('posts_per_week', 'منشورات في الأسبوع', ['type' => 'number', 'default' => 3, 'placeholder' => '3', 'hint' => 'من 2 إلى 5']),
                    self::f('goal', 'الهدف', ['type' => 'select', 'options' => 'goals', 'source' => 'goal']),
                ],
                'example_input' => ['offer' => 'سيروم طبيعي بلا عطور', 'audience' => 'نساء ذوات بشرة حساسة', 'channel' => 'instagram', 'weeks' => '4', 'posts_per_week' => 3, 'goal' => 'leads'],
                'output_template' => <<<'MD'
# تقويم المحتوى — {{channel_label}} · {{weeks}} أسابيع

لـ**{{audience}}** · العرض: {{offer}} · الهدف: {{goal_label}} · {{total_posts}} منشوراً من {{start_date}} إلى {{end_date}}

{{calendar_table}}

## المقياس وقاعدة الإيقاف
- المؤشر الأسبوعي: {{metric_label}}. الحد الأدنى المقبول بعد الأسبوع الثاني: {{metric_floor}}.
- إن بقيت الركيزة نفسها تحت الحد أسبوعين متتاليين، أوقفها وبدّل الصيغة لا القناة.

## الخطر وحلّه
- **الخطر**: الانقطاع بعد الأسبوع الثاني. **الحل**: حضّر منشورات الأسبوعين الأولين كاملة قبل أول نشر.

_أُنشئ في مختبر أثر — {{date}}_
MD,
            ],

            'email-sequence' => [
                'name' => 'تسلسل البريد', 'category' => 'المحتوى', 'credit_cost' => 2, 'output_type' => 'email_sequence', 'course_slug' => 'email-and-crm',
                'description' => 'تسلسل من خمس رسائل بعد التسجيل: ترحيب، المشكلة، الدليل، العرض، آخر فرصة؛ مع يوم الإرسال والعنوان وهيكل كل رسالة.',
                'tagline' => 'تُدخل: العرض والجمهور والهدف والمشكلة والدليل → تخرج: 5 رسائل بالعناوين وأيام الإرسال ومؤشر لكل رسالة',
                'feeds_from' => ['profile', 'value_proposition', 'headlines_cta'], 'feeds_into' => ['utm_links'],
                'quality_rules' => ['required' => ['goal_number_date', 'single_audience', 'metric_stop_rule'], 'advisory' => []],
                'input_schema' => [
                    self::f('offer', 'العرض', ['source' => 'offer', 'from_asset' => ['type' => 'value_proposition', 'key' => 'offer']]),
                    self::f('audience', 'الجمهور (شريحة واحدة)', ['source' => 'audience', 'from_asset' => ['type' => 'value_proposition', 'key' => 'audience']]),
                    self::f('goal', 'هدف التسلسل', ['type' => 'select', 'options' => 'goals', 'source' => 'goal']),
                    self::f('pain', 'المشكلة التي يعيشها القارئ', ['type' => 'textarea', 'from_asset' => ['type' => 'value_proposition', 'key' => 'pain']]),
                    self::f('proof', 'الدليل', ['from_asset' => ['type' => 'value_proposition', 'key' => 'proof']]),
                    self::f('days', 'مدة التسلسل', ['type' => 'select', 'options' => ['7' => '7 أيام', '10' => '10 أيام', '14' => '14 يوماً'], 'default' => '7']),
                ],
                'example_input' => ['offer' => 'سيروم طبيعي بلا عطور', 'audience' => 'نساء ذوات بشرة حساسة', 'goal' => 'sales', 'pain' => 'التهيّج المتكرر من المنتجات المعطّرة', 'proof' => 'شهادة اختبار جلدي و120 تقييماً', 'days' => '7'],
                'output_template' => <<<'MD'
# تسلسل البريد — {{offer}} ({{days}} أيام)

لـ**{{audience}}** · الهدف: {{goal_label}} · الهدف الرقمي: {{target_line}}

{{emails_table}}

## المقياس وقاعدة الإيقاف
- المؤشر: معدل النقر إلى الصفحة ({{click_floor}} على الأقل) ومعدل {{goal_label}} في نهاية التسلسل.
- إن كان معدل فتح الرسالة الثانية أقل من 25٪، أوقف التسلسل وبدّل العنوان قبل إرسال الثالثة.

## الخطر وحلّه
- **الخطر**: الرسائل كلها عن المنتج لا عن القارئ. **الحل**: الرسائل الثلاث الأولى عن المشكلة «{{pain_short}}» والدليل، والعرض في الرابعة فقط.

_أُنشئ في مختبر أثر — {{date}}_
MD,
            ],

            'utm-builder' => [
                'name' => 'بانِي UTM', 'category' => 'القياس', 'credit_cost' => 0, 'output_type' => 'utm_links', 'course_slug' => 'measurement-analytics-cro',
                'description' => 'ابنِ روابط تتبع UTM صحيحة بقاعدة تسمية موحدة، مع ثلاث نسخ للاختبار وجدول يقرأه التحليلات بلا فوضى.',
                'tagline' => 'تُدخل: الرابط والحملة والقناة والوسيط → تخرج: روابط UTM جاهزة بقاعدة تسمية وجدول تتبع',
                'feeds_from' => ['campaign_brief', 'content_calendar', 'email_sequence'], 'feeds_into' => ['landing_review'],
                'quality_rules' => ['required' => ['single_channel'], 'advisory' => ['metric_stop_rule']],
                'input_schema' => [
                    self::f('url', 'رابط الصفحة', ['placeholder' => 'https://example.com/offer', 'hint' => 'بلا معاملات utm']),
                    self::f('campaign', 'اسم الحملة', ['from_asset' => ['type' => 'campaign_brief', 'key' => 'offer'], 'placeholder' => 'مثال: serum-launch-nov']),
                    self::f('channel', 'القناة (المصدر)', ['type' => 'select', 'options' => 'channels', 'source' => 'channel']),
                    self::f('medium', 'الوسيط', ['type' => 'select', 'options' => ['cpc' => 'إعلان مدفوع (cpc)', 'social' => 'منشور عضوي (social)', 'email' => 'بريد (email)', 'whatsapp' => 'واتساب (whatsapp)', 'referral' => 'إحالة (referral)'], 'default' => 'cpc']),
                    self::f('content', 'وصف المحتوى (اختياري)', ['placeholder' => 'مثال: video-a', 'required' => false]),
                ],
                'example_input' => ['url' => 'https://example.com/serum', 'campaign' => 'إطلاق السيروم نوفمبر', 'channel' => 'instagram', 'medium' => 'cpc', 'content' => 'video-a'],
                'output_template' => <<<'MD'
# روابط UTM — {{campaign_slug}}

| المعامل | القيمة |
|---|---|
| utm_source | {{utm_source}} |
| utm_medium | {{utm_medium}} |
| utm_campaign | {{utm_campaign}} |
| utm_content | {{utm_content_line}} |

## الرابط الأساسي
`{{utm_url}}`

## ثلاث نسخ للاختبار (A/B/C)
{{variants_table}}

## قاعدة التسمية (التزم بها في كل الحملات)
- أحرف لاتينية صغيرة وشُرَط `-` فقط؛ لا مسافات ولا عربية داخل المعاملات.
- المصدر = المنصة، الوسيط = نوع الزيارة، الحملة = الحملة + الشهر، المحتوى = الإعلان أو المنشور.
- المقياس: نسبة الزيارات ذات utm كاملة من إجمالي زيارات الحملة ≥ 90٪؛ إن قلّت فالروابط تُنسخ بلا معاملات، أوقف النشر وصحّح القالب.

_أُنشئت في مختبر أثر — {{date}}_
MD,
            ],

            'landing-page-reviewer' => [
                'name' => 'مراجع صفحة الهبوط', 'category' => 'الإعلانات', 'credit_cost' => 3, 'output_type' => 'landing_review', 'course_slug' => 'measurement-analytics-cro',
                'description' => 'افحص صفحة الهبوط على ستة معايير (الوعد، التطابق مع الإعلان، الدليل، الخطوة التالية، النموذج، السرعة) واخرج بدرجة وقائمة تصحيح مرتبة.',
                'tagline' => 'تُدخل: العنوان والوعد والدليل والزر وعدد الحقول والسرعة → تخرج: درجة من 100 وقائمة إصلاحات مرتبة بالأثر',
                'feeds_from' => ['value_proposition', 'headlines_cta', 'utm_links'], 'feeds_into' => ['campaign_brief'],
                'quality_rules' => ['required' => ['metric_stop_rule', 'risk_mitigation'], 'advisory' => ['single_audience']],
                'input_schema' => [
                    self::f('url', 'رابط الصفحة (اختياري)', ['placeholder' => 'https://…', 'required' => false]),
                    self::f('headline', 'عنوان الصفحة الحالي', ['from_asset' => ['type' => 'headlines_cta', 'key' => 'benefit'], 'placeholder' => 'انسخ العنوان كما هو']),
                    self::f('promise', 'ما الذي تعد به الصفحة؟', ['from_asset' => ['type' => 'value_proposition', 'key' => 'differentiator']]),
                    self::f('proof', 'الأدلة الموجودة في الصفحة', ['from_asset' => ['type' => 'value_proposition', 'key' => 'proof'], 'placeholder' => 'تقييمات، شهادات، أرقام، ضمان', 'required' => false]),
                    self::f('cta', 'نص الزر الأساسي', ['placeholder' => 'مثال: اطلب الآن']),
                    self::f('fields_count', 'عدد حقول النموذج', ['type' => 'number', 'default' => 3]),
                    self::f('load', 'سرعة التحميل على الجوال', ['type' => 'select', 'options' => ['fast' => 'أقل من 3 ثوانٍ', 'medium' => '3–5 ثوانٍ', 'slow' => 'أكثر من 5 ثوانٍ'], 'default' => 'medium']),
                ],
                'example_input' => ['url' => 'https://example.com/serum', 'headline' => 'سيروم طبيعي للبشرة الحساسة', 'promise' => 'بشرة هادئة بلا تهيّج خلال 7 أيام', 'proof' => '120 تقييماً وشهادة اختبار جلدي', 'cta' => 'اطلب الآن', 'fields_count' => 5, 'load' => 'medium'],
                'output_template' => <<<'MD'
# مراجعة صفحة الهبوط — {{score}}/100

{{url_line}}

{{criteria_table}}

## قائمة التصحيح (مرتبة بالأثر)
{{fixes_list}}

## المقياس وقاعدة الإيقاف
- المؤشر: معدل التحويل من الزيارة إلى {{cta}}. الحد الأدنى المقبول للصفحات الإعلانية: 2٪.
- إن بقي أقل من 1٪ بعد 300 زيارة، أوقف الإعلان وطبّق أول تصحيحَين قبل إعادة التشغيل.

## الخطر وحلّه
- **الخطر**: {{top_risk}}. **الحل**: {{top_fix}}

_أُنشئت في مختبر أثر — {{date}}_
MD,
            ],

            'prompt-builder' => [
                'name' => 'بانِي Prompts', 'category' => 'الذكاء الاصطناعي', 'credit_cost' => 0, 'output_type' => 'prompt_pack', 'course_slug' => 'ai-in-marketing',
                'description' => 'ابنِ أمر ذكاء اصطناعي منضبطاً: دور، سياق من مشروعك، مهمة، صيغة مخرج، قيود، ثم قائمة مراجعة لما يعيده النموذج.',
                'tagline' => 'تُدخل: المهمة والجمهور والعرض والنبرة والقيود → تخرج: Prompt منظّم بالسياق + قائمة مراجعة المخرج',
                'feeds_from' => ['profile', 'customer_persona', 'value_proposition'], 'feeds_into' => ['content_angles', 'headlines_cta'],
                'quality_rules' => ['required' => ['single_audience', 'risk_mitigation'], 'advisory' => []],
                'input_schema' => [
                    self::f('task', 'المهمة', ['type' => 'select', 'options' => ['ad' => 'كتابة إعلان', 'angles' => 'أفكار محتوى', 'objection' => 'الرد على اعتراض', 'competitor' => 'تحليل منافس', 'summary' => 'تلخيص تقرير أو مقال'], 'default' => 'ad']),
                    self::f('audience', 'الجمهور (شريحة واحدة)', ['source' => 'audience', 'from_asset' => ['type' => 'customer_persona', 'key' => 'audience']]),
                    self::f('offer', 'العرض', ['source' => 'offer', 'from_asset' => ['type' => 'value_proposition', 'key' => 'offer']]),
                    self::f('tone', 'النبرة', ['type' => 'select', 'options' => ['warm' => 'ودودة ومباشرة', 'expert' => 'خبيرة وهادئة', 'bold' => 'جريئة ومختصرة'], 'default' => 'warm']),
                    self::f('constraints', 'قيود إضافية', ['type' => 'textarea', 'placeholder' => 'مثال: لا أرقام بلا مصدر، 60 كلمة كحد أقصى، بالعربية الفصحى المبسطة', 'required' => false]),
                ],
                'example_input' => ['task' => 'ad', 'audience' => 'نساء 25–40 ذوات بشرة حساسة', 'offer' => 'سيروم طبيعي بلا عطور', 'tone' => 'warm', 'constraints' => 'لا تذكر أرقاماً بلا مصدر، 60 كلمة كحد أقصى'],
                'output_template' => <<<'MD'
# حزمة Prompts — {{task_label}}

## الأمر الجاهز (انسخه إلى أي نموذج)
```
الدور: أنت مسوّق عربي خبير يكتب لـ{{audience}} بنبرة {{tone_label}}.
السياق: العرض هو «{{offer}}». الجمهور شريحة واحدة: {{audience}}.
المهمة: {{task_instruction}}
الصيغة: {{task_format}}
القيود: لا تخترع أرقاماً أو مصادر؛ لا تدّعِ نتائج غير مثبتة؛ العربية الفصحى المبسطة. {{constraints_line}}
قبل أن تجيب: اطرح سؤالاً واحداً إن كان السياق ناقصاً، وإلا نفّذ مباشرة.
```

## قائمة مراجعة المخرج (قبل أي استخدام)
- هل كل رقم أو ادعاء في المخرج له مصدر تملكه أنت؟ احذف ما لا مصدر له.
- هل بقي الكلام لشريحة واحدة ({{audience}}) أم اتّسع؟
- هل النبرة {{tone_label}} فعلاً أم عامة؟
- هل الدعوة لاتخاذ إجراء واحدة وواضحة؟

## الخطر وحلّه
- **الخطر**: نشر مخرج الذكاء الاصطناعي كما هو بادعاءات لا تملك دليلها. **الحل**: المراجعة البشرية بالقائمة أعلاه إلزامية، والمخرج مسودة لا منشور.

_أُنشئت في مختبر أثر — {{date}}_
MD,
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function calculators(): array
    {
        return [
            'cac' => ['name' => 'حاسبة CAC', 'category' => 'القياس', 'description' => 'تكلفة اكتساب العميل = إجمالي الإنفاق التسويقي ÷ عدد العملاء الجدد في الفترة نفسها.', 'tagline' => 'تُدخل: الإنفاق وعدد العملاء الجدد → تخرج: CAC ومعياره المرجعي', 'input_schema' => [['key' => 'spend', 'label' => 'إجمالي الإنفاق التسويقي في الفترة (ريال)', 'type' => 'number', 'default' => 3000], ['key' => 'customers', 'label' => 'عدد العملاء الجدد في الفترة', 'type' => 'number', 'default' => 60]], 'course_slug' => 'measurement-analytics-cro', 'example_input' => ['spend' => 3000, 'customers' => 60]],
            'ltv' => ['name' => 'حاسبة LTV', 'category' => 'القياس', 'description' => 'القيمة الدائمة للعميل = متوسط الطلب × الطلبات في السنة × سنوات البقاء × هامش الربح.', 'tagline' => 'تُدخل: متوسط الطلب والتكرار والبقاء والهامش → تخرج: LTV ونسبته إلى CAC', 'input_schema' => [['key' => 'avg_order', 'label' => 'متوسط قيمة الطلب (ريال)', 'type' => 'number', 'default' => 180], ['key' => 'orders_per_year', 'label' => 'عدد الطلبات في السنة للعميل الواحد', 'type' => 'number', 'default' => 4], ['key' => 'years', 'label' => 'سنوات بقاء العميل', 'type' => 'number', 'default' => 2], ['key' => 'margin', 'label' => 'هامش الربح الإجمالي (٪)', 'type' => 'number', 'default' => 40], ['key' => 'cac', 'label' => 'تكلفة اكتساب العميل إن عرفتها (ريال، اختياري)', 'type' => 'number', 'default' => 50, 'required' => false]], 'course_slug' => 'measurement-analytics-cro', 'example_input' => ['avg_order' => 180, 'orders_per_year' => 4, 'years' => 2, 'margin' => 40, 'cac' => 50]],
            'roas' => ['name' => 'حاسبة ROAS', 'category' => 'الإعلانات', 'description' => 'العائد على الإنفاق الإعلاني = الإيراد من الإعلانات ÷ الإنفاق الإعلاني، ونقطة التعادل من هامش الربح.', 'tagline' => 'تُدخل: الإيراد والإنفاق والهامش → تخرج: ROAS ونقطة التعادل', 'input_schema' => [['key' => 'revenue', 'label' => 'الإيراد المنسوب للإعلانات (ريال)', 'type' => 'number', 'default' => 9000], ['key' => 'spend', 'label' => 'الإنفاق الإعلاني (ريال)', 'type' => 'number', 'default' => 3000], ['key' => 'margin', 'label' => 'هامش الربح الإجمالي (٪)', 'type' => 'number', 'default' => 40]], 'course_slug' => 'paid-ads', 'example_input' => ['revenue' => 9000, 'spend' => 3000, 'margin' => 40]],
            'conversion-sample' => ['name' => 'معدل التحويل وحجم العينة', 'category' => 'القياس', 'description' => 'معدل التحويل من الزيارات، وحجم العينة اللازم لكل نسخة قبل أن تحكم على اختبار A/B.', 'tagline' => 'تُدخل: الزيارات والتحويلات والتحسين المطلوب → تخرج: المعدل وحجم العينة لكل نسخة', 'input_schema' => [['key' => 'visitors', 'label' => 'عدد الزيارات', 'type' => 'number', 'default' => 2000], ['key' => 'conversions', 'label' => 'عدد التحويلات', 'type' => 'number', 'default' => 40], ['key' => 'lift', 'label' => 'أصغر تحسين تريد كشفه (٪ نسبي)', 'type' => 'number', 'default' => 20]], 'course_slug' => 'measurement-analytics-cro', 'example_input' => ['visitors' => 2000, 'conversions' => 40, 'lift' => 20]],
        ];
    }

    public static function simulator(): array
    {
        return [
            'name' => 'محاكي الحملة الإعلانية', 'category' => 'الإعلانات', 'course_slug' => 'paid-ads',
            'description' => 'ميزانية + حجم جمهور + قناة ← تقدير الوصول والنقرات والطلبات وتكلفة الطلب والمخاطر، بقواعد ومعايير مُعلنة في الصفحة.',
            'tagline' => 'تُدخل: الميزانية وحجم الجمهور والقناة ومعدل التحويل → تخرج: نطاق النتائج ومخاطر الحملة قبل الإنفاق',
            'input_schema' => [
                ['key' => 'budget', 'label' => 'الميزانية الشهرية (ريال)', 'type' => 'number', 'default' => 3000],
                ['key' => 'audience_size', 'label' => 'حجم الجمهور المستهدف (أشخاص)', 'type' => 'number', 'default' => 150000],
                ['key' => 'channel', 'label' => 'القناة', 'type' => 'select', 'options' => 'channels', 'default' => 'instagram'],
                ['key' => 'cvr', 'label' => 'معدل التحويل من النقرة إلى طلب (٪)', 'type' => 'number', 'default' => 3],
            ],
            'example_input' => ['budget' => 3000, 'audience_size' => 150000, 'channel' => 'instagram', 'cvr' => 3],
        ];
    }

    /** Options map for a select field. */
    public static function options(array $field): array
    {
        $options = $field['options'] ?? [];
        if (is_array($options)) {
            return $options;
        }

        return match ($options) {
            'channels' => Catalog::CHANNELS,
            'goals' => Catalog::GOALS,
            'sectors' => Catalog::SECTORS,
            default => [],
        };
    }
}
