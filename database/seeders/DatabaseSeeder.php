<?php

namespace Database\Seeders;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\LearningPath;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Lab\Models\Tool;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('plans')->insertOrIgnore([
            'slug' => 'pro', 'name' => 'الخطة الاحترافية',
            'provider_price_id' => null,
            'price' => (int) config('services.payments.plans.pro.amount') ?: 1300,
            'currency' => config('services.payments.plans.pro.currency') ?: 'USD',
            'credits' => (int) config('services.payments.plans.pro.credits') ?: 100,
            'entitlements' => json_encode(['ai_tools' => true, 'projects' => 'unlimited']),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $articles = [
            ['type' => 'guide', 'title' => 'دليل عملي لبناء استراتيجية تسويق رقمي قابلة للقياس', 'slug' => 'digital-marketing-strategy-guide', 'eyebrow' => 'دليل تطبيقي', 'topic' => 'الاستراتيجية', 'excerpt' => 'حوّل أهداف المشروع إلى جمهور ورسالة وقنوات ومؤشرات أداء يمكن متابعتها أسبوعياً.', 'body' => "الاستراتيجية الجيدة ليست قائمة قنوات، بل سلسلة قرارات تبدأ بهدف تجاري واضح.\n\nابدأ بتحديد النتيجة المطلوبة، ثم اختر شريحة واحدة ورسالة واحدة واختباراً صغيراً. بعد ذلك اربط كل قناة بمؤشر يقيس التقدم الحقيقي، لا الضجيج.", 'reading_minutes' => 9],
            ['type' => 'article', 'title' => 'كيف تستخدم الذكاء الاصطناعي في التسويق دون محتوى مكرر؟', 'slug' => 'responsible-marketing-ai', 'eyebrow' => 'ذكاء اصطناعي', 'topic' => 'الذكاء الاصطناعي', 'excerpt' => 'منهج عملي يجعل الذكاء الاصطناعي مساعداً للبحث والتحليل والتنفيذ، لا بديلاً عن الخبرة.', 'body' => "ابدأ بالسياق لا بالأمر. عرّف السوق والجمهور والهدف والأدلة المتاحة قبل أن تطلب مسودة.\n\nاستخدم النموذج لتوسيع الخيارات وكشف الفجوات، ثم راجع الادعاءات والأرقام والصوت التحريري بشرياً قبل النشر.", 'reading_minutes' => 7],
            ['type' => 'article', 'title' => 'خريطة رحلة العميل من أول بحث حتى الشراء', 'slug' => 'customer-journey-map', 'eyebrow' => 'تجربة العميل', 'topic' => 'النمو', 'excerpt' => 'طريقة مختصرة لرؤية نقاط الاحتكاك والفرص في كل مرحلة من رحلة العميل.', 'body' => "قسّم الرحلة إلى اكتشاف وتقييم وقرار واستخدام وعودة. في كل مرحلة سجّل سؤال العميل ودليله المطلوب والعائق الذي يمنعه من التقدم.\n\nاربط كل نقطة محتوى أو تواصل بفعل تالٍ واحد واضح.", 'reading_minutes' => 6],
            ['type' => 'translated_analysis', 'title' => 'اتجاهات البحث المدعوم بالذكاء الاصطناعي: ماذا تعني للمسوّق العربي؟', 'slug' => 'ai-search-arabic-marketers', 'eyebrow' => 'رؤية عالمية', 'topic' => 'البحث وGEO', 'excerpt' => 'تحليل عربي لأثر محركات الإجابة على بنية المحتوى، المصادر، والظهور العضوي.', 'body' => "تفضّل محركات الإجابة المحتوى الواضح القابل للإسناد. هذا يرفع قيمة التعريفات الدقيقة والمصادر الأولية وتاريخ التحديث وهوية الكاتب.\n\nبالنسبة للسوق العربي، توجد فرصة في تقديم أمثلة محلية ومصطلحات منضبطة بدلاً من ترجمة سطحية للمحتوى الإنجليزي.", 'reading_minutes' => 8, 'source_mode' => 'analysis'],
            ['type' => 'news', 'title' => 'كيف تقرأ تحديثات منصات الإعلانات قبل تعديل حملاتك؟', 'slug' => 'ad-platform-updates-framework', 'eyebrow' => 'تحديثات', 'topic' => 'الإعلانات', 'excerpt' => 'إطار تحليلي يفرّق بين الإعلان التسويقي والتغيير المؤثر فعلاً على الأداء.', 'body' => "لا تغيّر الحملة بمجرد إعلان ميزة جديدة. افحص نطاق التحديث، الحسابات المشمولة، أثر القياس، وفترة التعلم.\n\nاختبر التغيير على جزء محدود وسجّل خط الأساس قبل المقارنة.", 'reading_minutes' => 5, 'source_mode' => 'summary'],
            ['type' => 'case_study', 'title' => 'من محتوى متفرق إلى عنقود موضوعي يقود للطلب', 'slug' => 'topic-cluster-case-study', 'eyebrow' => 'دراسة حالة تعليمية', 'topic' => 'المحتوى', 'excerpt' => 'مثال تعليمي يوضح كيف تربط صفحة ركيزة بالمقالات والأدوات والدروس.', 'body' => "هذا مثال تعليمي وليس نتيجة عميل حقيقية. بدأ السيناريو بحصر الأسئلة المتكررة، ثم تجميعها حول قرار شراء واحد.\n\nأُنشئت صفحة ركيزة وروابط داخلية وأداة مجانية، وأصبحت كل قطعة تقود إلى خطوة عملية بدلاً من نهاية مسدودة.", 'reading_minutes' => 7],
            ['type' => 'guide', 'title' => 'دليل بناء نشرة بريدية تخدم قراراً تجارياً', 'slug' => 'email-newsletter-business-guide', 'eyebrow' => 'دليل تطبيقي', 'topic' => 'البريد الإلكتروني', 'excerpt' => 'من الوعد التحريري إلى القياس دون مطاردة أرقام الفتح وحدها.', 'body' => "اختر وعداً واحداً للنشرة يرتبط بمشكلة متكررة لدى جمهورك. اجعل كل عدد يقود إلى قرار أو تطبيق صغير.\n\nقِس الردود والزيارات المؤهلة والطلبات الناتجة، ثم طوّر الوعد والوتيرة على أساس السلوك الفعلي.", 'reading_minutes' => 8],
            ['type' => 'article', 'title' => 'ما الذي يجب قياسه قبل زيادة ميزانية الإعلان؟', 'slug' => 'before-scaling-ad-budget', 'eyebrow' => 'القياس', 'topic' => 'الإعلانات', 'excerpt' => 'قائمة تحقق تربط التتبع وجودة الطلب وربحية الوحدة بقرار التوسع.', 'body' => "لا توسّع إن كان تعريف التحويل أو التتبع غير مستقر. افحص جودة الطلبات لا عددها فقط، وتأكد من قدرة التشغيل على استيعاب الزيادة.\n\nارفع الميزانية تدريجياً مع نافذة قرار ثابتة وسجل يوضح سبب كل تعديل.", 'reading_minutes' => 6],
            ['type' => 'translated_analysis', 'title' => 'المحتوى المفيد في عصر الإجابات التوليدية: إطار للسوق العربي', 'slug' => 'helpful-content-generative-answers', 'eyebrow' => 'رؤية عالمية', 'topic' => 'SEO وGEO', 'excerpt' => 'قراءة عربية لإشارات الخبرة والموثوقية وبنية الإجابة القابلة للاقتباس.', 'body' => "تزيد قيمة المحتوى عندما يجيب عن سؤال محدد بأدلة واضحة وهوية تحريرية معروفة. لا يكفي تكرار تعريفات عامة موجودة في عشرات الصفحات.\n\nفي العربية، أضف سياقاً محلياً ومصطلحات منضبطة وأمثلة قابلة للتحقق، مع فصل الحقيقة عن الرأي.", 'reading_minutes' => 9, 'source_mode' => 'analysis'],
        ];

        foreach ($articles as $index => $data) {
            if (in_array($data['type'], ['translated_analysis', 'news'], true)) {
                $data['meta'] = ['arabic_context' => 'تحليل يربط المصدر العالمي بقرارات التسويق في السوق العربي.'];
            }
            $item = ContentItem::query()->create($data + ['status' => 'published', 'author_name' => 'فريق أثر', 'reviewer_name' => 'خالد سعد', 'reviewed_at' => now()->subDays($index + 1), 'published_at' => now()->subDays($index + 1)]);
            $item->sources()->create(['publisher' => $data['type'] === 'translated_analysis' ? 'Google Search Central' : 'منهجية أثر', 'title' => $data['type'] === 'translated_analysis' ? 'إرشادات إنشاء محتوى مفيد وموثوق' : 'مرجع تحريري داخلي', 'url' => $data['type'] === 'translated_analysis' ? 'https://developers.google.com/search/docs/fundamentals/creating-helpful-content' : 'https://khaledsaad.net/methodology', 'published_on' => now()->subMonth(), 'language' => $data['type'] === 'translated_analysis' ? 'en' : 'ar']);
        }

        $path = LearningPath::query()->create(['title' => 'مسار صاحب المشروع', 'slug' => 'business-owner-path', 'summary' => 'من تشخيص الأولوية إلى تجربة تسويقية قابلة للقياس.', 'audience' => 'أصحاب المشاريع', 'is_published' => true]);
        $course = Course::query()->create(['title' => 'أساسيات التسويق الرقمي من القرار إلى القياس', 'slug' => 'digital-marketing-foundations', 'level' => 'مبتدئ', 'summary' => 'مسار تطبيقي يبني استراتيجية أولية ويحوّلها إلى تجربة قابلة للقياس.', 'duration_minutes' => 120, 'is_published' => true]);
        $path->courses()->attach($course, ['position' => 1]);
        $module = $course->modules()->create(['title' => 'من القرار إلى التجربة', 'slug' => 'decision-to-experiment', 'outcome' => 'خطة أولية وتجربة أسبوعية.', 'position' => 1]);
        $lessons = $course->lessons()->createMany([
            ['title' => 'اختيار الهدف والجمهور', 'slug' => 'goal-and-audience', 'objective' => 'صياغة هدف تجاري وشريحة أساسية.', 'content' => 'حدّد نتيجة واحدة، ثم صِف الشريحة من خلال المشكلة والسياق ودافع القرار.', 'position' => 1, 'duration_minutes' => 25, 'is_preview' => true],
            ['title' => 'بناء الرسالة والتجربة', 'slug' => 'message-and-experiment', 'objective' => 'تحويل الفرضية إلى رسالة واختبار.', 'content' => 'اكتب وعداً واضحاً ودليلاً وخطوة تالية، ثم اختر قناة واحدة للاختبار.', 'position' => 2, 'duration_minutes' => 35],
            ['title' => 'لوحة القياس الأسبوعية', 'slug' => 'weekly-scorecard', 'objective' => 'اختيار مؤشرات مرتبطة بالهدف.', 'content' => 'راقب مؤشراً قيادياً وآخر نهائياً، وسجّل القرار الناتج عن كل قراءة.', 'position' => 3, 'duration_minutes' => 30],
        ]);
        foreach ($lessons as $lesson) {
            $lesson->update(['course_module_id' => $module->id]);
            $lesson->activities()->create([
                'title' => 'مخرج تطبيقي: '.$lesson->title,
                'kind' => 'deliverable',
                'instructions' => 'طبّق الفكرة على مشروعك واكتب قرارك التالي.',
                'position' => 1,
            ]);
        }

        DB::table('resource_templates')->insert([
            ['name' => 'قالب موجز الحملة', 'slug' => 'campaign-brief-template', 'description' => 'أسئلة منظمة لتحويل الهدف إلى موجز.', 'template' => json_encode(['goal', 'audience', 'offer', 'message', 'measurement']), 'is_published' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'قالب لوحة القياس الأسبوعية', 'slug' => 'weekly-scorecard-template', 'description' => 'مؤشر وقرار وتجربة تالية.', 'template' => json_encode(['leading_metric', 'outcome_metric', 'decision', 'next_test']), 'is_published' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        foreach ([
            ['name' => 'باني موجز الحملة', 'slug' => 'campaign-brief-builder', 'category' => 'التخطيط', 'description' => 'حوّل الهدف والجمهور والعرض إلى موجز حملة من صفحة واحدة.', 'kind' => 'deterministic', 'credit_cost' => 0],
            ['name' => 'مولّد زوايا المحتوى', 'slug' => 'content-angle-generator', 'category' => 'المحتوى', 'description' => 'استخرج زوايا متنوعة مرتبطة بمرحلة العميل ودليل الرسالة.', 'kind' => 'ai', 'credit_cost' => 2],
            ['name' => 'مراجع صفحة الهبوط', 'slug' => 'landing-page-reviewer', 'category' => 'التحويل', 'description' => 'افحص وضوح العرض والثقة والخطوة التالية في صفحة الهبوط.', 'kind' => 'ai', 'credit_cost' => 3],
        ] as $tool) {
            $createdTool = Tool::query()->create($tool + ['is_published' => true, 'input_schema' => ['goal', 'audience', 'offer']]);
            $createdTool->versions()->create([
                'version' => 1,
                'input_schema' => ['goal', 'audience', 'offer'],
                'output_schema' => ['text', 'tokens', 'model'],
                'prompt_key' => $tool['kind'] === 'ai' ? 'marketing-ar-v1' : null,
                'config_hash' => hash('sha256', json_encode($tool, JSON_UNESCAPED_UNICODE)),
                'is_active' => true,
            ]);
        }

        $demoUser = User::forceCreate([
            'name' => 'حساب العرض التعليمي', 'email' => 'demo@example.invalid',
            'email_verified_at' => now(), 'password' => Hash::make(Str::random(40)),
        ]);
        $demoWorkspace = Workspace::query()->create(['name' => 'مساحة العرض التعليمية', 'slug' => 'illustrative-demo']);
        $demoWorkspace->members()->attach($demoUser, ['role' => 'owner']);
        $demoWorkspace->projects()->create([
            'name' => 'مثال تعليمي: إطلاق خدمة استشارية', 'slug' => 'illustrative-consulting-launch',
            'profile' => ['illustrative' => true, 'goal' => 'اختبار الطلب دون ادعاء نتائج حقيقية'],
        ]);
    }
}
