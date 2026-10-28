<?php

namespace Database\Seeders;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\LearningPath;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Academy\Models\ResourceTemplate;
use App\Domain\Lab\Models\Tool;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * الأكاديمية (blueprint §4.3): البرنامجان، المسارات الـ12 بأسمائها الثابتة، ومسار «أساسيات التسويق وفهم السوق» بستة دروس كاملة.
 * المسارات 2–12 بطاقات «قريباً» (is_published=false) لا تُفهرس ولا تُفتح.
 */
class AcademySeeder extends Seeder
{
    public const COURSES = [
        ['marketing-foundations', 'أساسيات التسويق وفهم السوق', 'مبتدئ', 150, 'خطة التسويق الأولى لمشروعك: قيمة، شريحة، رسالة، قناة، وقياس أسبوعي', 'أساسيات التسويق'],
        ['audience-and-personas', 'الجمهور والشخصيات', 'مبتدئ', 120, 'شخصية عميل موثّقة ببيانات حقيقية', 'الجمهور'],
        ['offer-value-positioning', 'العرض والقيمة والتموضع', 'متوسط', 120, 'عرض قيمة وتموضع مكتوبان ومختبران', 'العرض'],
        ['content-writing-persuasion', 'كتابة المحتوى والإقناع', 'مبتدئ', 150, 'ثلاثة نصوص إقناعية جاهزة للنشر', 'المحتوى'],
        ['social-media', 'السوشيال ميديا', 'مبتدئ', 150, 'تقويم محتوى شهري بزوايا محددة', 'السوشيال ميديا'],
        ['paid-ads', 'الإعلانات المدفوعة', 'متوسط', 180, 'هيكل حملة إعلانية بقواعد إيقاف وتوسّع', 'الإعلانات'],
        ['seo-content-marketing', 'SEO والتسويق بالمحتوى', 'متوسط', 180, 'عنقود موضوعي بصفحة ركيزة ومقالات داعمة', 'SEO'],
        ['email-and-crm', 'البريد وCRM', 'متوسط', 150, 'تسلسل بريدي من خمس رسائل مرتبط بمرحلة العميل', 'البريد'],
        ['measurement-analytics-cro', 'القياس والتحليلات وCRO', 'متقدم', 180, 'لوحة قياس وخطة اختبارات تحويل', 'القياس'],
        ['ai-in-marketing', 'الذكاء الاصطناعي في التسويق', 'متوسط', 150, 'مكتبة Prompts تسويقية مرتبطة بسياق مشروعك', 'الذكاء الاصطناعي'],
        ['automation-workflows', 'الأتمتة وسير العمل', 'متقدم', 150, 'ثلاثة سير عمل مؤتمتة للمتابعة والتأهيل', 'الأتمتة'],
        ['integrated-marketing-plan', 'بناء خطة التسويق المتكاملة', 'متقدم', 240, 'خطة تسويق متكاملة لربع سنة بميزانية ومؤشرات', 'الخطة المتكاملة'],
    ];

    /** Program orders differ: owners start with fundamentals and channels; marketers go deeper into audience/offer first and skip nothing. */
    public const OWNER_ORDER = ['marketing-foundations', 'audience-and-personas', 'offer-value-positioning', 'social-media', 'content-writing-persuasion', 'paid-ads', 'email-and-crm', 'measurement-analytics-cro', 'ai-in-marketing', 'integrated-marketing-plan'];

    public const MARKETER_ORDER = ['marketing-foundations', 'audience-and-personas', 'offer-value-positioning', 'content-writing-persuasion', 'seo-content-marketing', 'paid-ads', 'social-media', 'email-and-crm', 'measurement-analytics-cro', 'ai-in-marketing', 'automation-workflows', 'integrated-marketing-plan'];

    public function run(): void
    {
        $labTool = Tool::query()->firstOrCreate(['slug' => 'lesson-lab'], [
            'name' => 'مختبر الدرس', 'category' => 'التخطيط', 'description' => 'المختبر المضمّن في الدروس: يحوّل التدريب الموجّه إلى أصل بقوالب وقواعد بلا ذكاء اصطناعي.',
            'kind' => 'deterministic', 'credit_cost' => 0, 'is_published' => false, 'input_schema' => [],
        ]);

        $courses = [];
        foreach (self::COURSES as $i => [$slug, $title, $level, $minutes, $outcome, $topic]) {
            $courses[$slug] = Course::query()->updateOrCreate(['slug' => $slug], [
                'title' => $title, 'level' => $level, 'duration_minutes' => $minutes, 'outcome' => $outcome, 'topic' => $topic, 'position' => $i + 1,
                'summary' => $this->summary($slug), 'is_published' => $slug === 'marketing-foundations',
            ]);
        }
        foreach (self::COURSES as $i => [$slug]) {
            $prev = self::COURSES[$i - 1][0] ?? null;
            $next = self::COURSES[$i + 1][0] ?? null;
            $courses[$slug]->update(['prerequisite_course_id' => $prev ? $courses[$prev]->id : null, 'next_course_id' => $next ? $courses[$next]->id : null]);
        }

        $owner = LearningPath::query()->updateOrCreate(['slug' => 'business-owners-program'], [
            'title' => 'برنامج أصحاب المشاريع', 'audience' => 'owner', 'position' => 1, 'is_published' => true,
            'summary' => 'تشخيص → خريطة أولويات → دروس عن مشروعك → أثر يُقاس. للمؤسس الذي يسوّق بنفسه أو بفريق صغير.',
            'outcome' => 'خطة تسويق تعمل لمشروعك بأرقامك أنت',
        ]);
        $marketer = LearningPath::query()->updateOrCreate(['slug' => 'marketers-program'], [
            'title' => 'برنامج المسوّقين', 'audience' => 'marketer', 'position' => 2, 'is_published' => true,
            'summary' => 'سلاسل أصول لعملائك، مشاركة بروابط، ومسارات متقدمة في القياس والأتمتة. للمسوّق المستقل والوكالة.',
            'outcome' => 'سلسلة أصول كاملة قابلة للمشاركة مع كل عميل',
        ]);
        $owner->courses()->sync(collect(self::OWNER_ORDER)->mapWithKeys(fn ($slug, $i) => [$courses[$slug]->id => ['position' => $i + 1]])->all());
        $marketer->courses()->sync(collect(self::MARKETER_ORDER)->mapWithKeys(fn ($slug, $i) => [$courses[$slug]->id => ['position' => $i + 1]])->all());

        $course = $courses['marketing-foundations'];
        $module = $course->modules()->updateOrCreate(['slug' => 'from-value-to-plan'], ['title' => 'من القيمة إلى الخطة الأولى', 'outcome' => 'خطة التسويق الأولى بأصول متراكمة.', 'position' => 1]);

        $lessonsBySlug = [];
        foreach (MarketingFoundationsContent::lessons() as $i => $data) {
            $template = ResourceTemplate::query()->updateOrCreate(['slug' => $data['template']['slug']], [
                'name' => $data['template']['name'], 'description' => $data['template']['description'], 'template' => $data['template']['fields'],
                'format' => 'markdown', 'body_markdown' => $data['template']['body'], 'is_published' => true,
            ]);
            $lesson = Lesson::query()->updateOrCreate(['course_id' => $course->id, 'slug' => $data['slug']], [
                'course_module_id' => $module->id, 'title' => $data['title'], 'objective' => $data['objective'], 'content' => $data['quick_summary'],
                'position' => $i + 1, 'duration_minutes' => $data['duration'], 'is_preview' => $i === 0,
                'goal' => $data['goal'], 'concept_body' => $data['concept'], 'common_mistake' => $data['common_mistake'], 'market_example' => $data['market_example'],
                'quick_summary' => $data['quick_summary'], 'scenario_template' => $data['scenario_template'], 'general_scenario' => MarketingFoundationsContent::GENERAL_SCENARIO,
                'guided_steps' => $data['guided_steps'], 'lab_tool_id' => $labTool->id, 'lab_config' => $data['lab'], 'whatif_rules' => $data['whatif'],
                'template_id' => $template->id, 'outcome_type' => $data['outcome_type'], 'outcome_title' => $data['outcome_title'],
                'real_action_template' => $data['real_action'], 'real_result_fields' => $data['real_result_fields'], 'published_at' => now()->subDay(),
            ]);
            $template->update(['lesson_id' => $lesson->id]);
            $activity = $lesson->activities()->updateOrCreate(['position' => 1], ['title' => 'أصل الدرس: '.$data['outcome_title'], 'kind' => 'deliverable', 'instructions' => 'أنشئ الأصل في المختبر واحفظه في مشروعك، ثم أجب عن أسئلة القرار.', 'is_required' => true]);
            $lesson->questions()->delete();
            $lesson->questions()->create(['activity_id' => $activity->id, 'kind' => 'decision', 'is_opening' => true, 'position' => 0, 'prompt' => $data['opening']['prompt'], 'scenario' => $data['opening']['scenario'], 'options' => $data['opening']['options']]);
            foreach ($data['questions'] as $qi => $q) {
                $lesson->questions()->create(['activity_id' => $activity->id, 'kind' => 'decision', 'is_opening' => false, 'position' => $qi + 1, 'prompt' => $q['prompt'], 'scenario' => $q['scenario'], 'options' => $q['options']]);
            }
            $lesson->assetLinks()->delete();
            foreach ($data['needs'] as $type => $label) {
                $lesson->assetLinks()->create(['required_tool_output_type' => $type, 'label' => $label]);
            }
            $lessonsBySlug[$data['slug']] = $lesson;
        }
        $slugs = array_keys($lessonsBySlug);
        foreach ($slugs as $i => $slug) {
            $lessonsBySlug[$slug]->update(['next_lesson_id' => isset($slugs[$i + 1]) ? $lessonsBySlug[$slugs[$i + 1]]->id : null]);
        }
        // Old demo lessons/course from the first seed version are replaced by the full-form course.
        DB::table('courses')->where('slug', 'digital-marketing-foundations')->delete();
    }

    private function summary(string $slug): string
    {
        return match ($slug) {
            'marketing-foundations' => 'ستة دروس عن مشروعك أنت: ما التسويق فعلاً، خريطة السوق، الشريحة الأولى، الرسالة الأساسية، القناة الأولى والميزانية، ثم خطة 4 أسابيع تُقاس في الواقع.',
            'audience-and-personas' => 'من الشريحة إلى شخصية عميل موثّقة: مقابلات، بيانات، ورحلة قرار.',
            'offer-value-positioning' => 'عرض قيمة يُفهم في ثلاث ثوانٍ وتموضع لا يُنافَس بالسعر.',
            'content-writing-persuasion' => 'هياكل إقناع عملية للنصوص القصيرة والطويلة بالعربية.',
            'social-media' => 'منصة واحدة، زوايا محتوى متكررة، وقياس يتجاوز الإعجابات.',
            'paid-ads' => 'بناء الحملة الأولى بقواعد إيقاف وتوسّع وقراءة تكلفة الطلب.',
            'seo-content-marketing' => 'عناقيد موضوعية وصفحات ركيزة تجلب طلباً مؤهلاً من البحث.',
            'email-and-crm' => 'تسلسلات بريدية ومتابعة CRM تحوّل الاهتمام إلى شراء وعودة.',
            'measurement-analytics-cro' => 'لوحات قياس، تجارب تحويل، وقراءة أرقام تقود القرار.',
            'ai-in-marketing' => 'استخدام نماذج اللغة في البحث والكتابة والتحليل بلا محتوى مكرر.',
            'automation-workflows' => 'سير عمل مؤتمتة للمتابعة والتأهيل والتقارير.',
            'integrated-marketing-plan' => 'جمع الأصول كلها في خطة ربع سنوية بميزانية ومؤشرات ومسؤوليات.',
            default => '',
        };
    }
}
