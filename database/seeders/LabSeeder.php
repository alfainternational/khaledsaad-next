<?php

namespace Database\Seeders;

use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Lab\Services\GeneratorEngine;
use App\Domain\Lab\Services\LabCatalog;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * المرحلة 2 — المختبر: the fifteen tools (10 generators, 4 calculators, 1 simulator) with their templates and chain,
 * version 1 of each generator template, and three anonymous illustrative examples per generator (blueprint §4.6, §4.7 item 5).
 * Idempotent: updateOrCreate by slug; re-running refreshes templates and examples without duplicating anything.
 * Run once on production via deploy/cpanel-bootstrap.sh (marker storage/app/.seeded-stage2).
 */
class LabSeeder extends Seeder
{
    /** Three anonymous example projects; every generator is run on each so learners can compare with real-looking assets. */
    public const EXAMPLE_PROJECTS = [
        ['name' => 'مثال: متجر عناية طبيعية', 'slug' => 'example-natural-care', 'input' => [
            'offer' => 'سيروم طبيعي بلا عطور للبشرة الحساسة', 'sector' => 'beauty', 'audience' => 'نساء 25–40 في الرياض ذوات بشرة حساسة', 'pain' => 'التهيّج المتكرر من المنتجات المعطّرة وفقدان الثقة بالادعاءات',
            'trigger' => 'تفاقم التهيّج قبل مناسبة عائلية قريبة', 'channel' => 'instagram', 'objection' => 'السعر أعلى من بدائل الصيدلية', 'differentiator' => 'تركيبة بلا عطور مختبَرة جلدياً مع ضمان استرجاع 14 يوماً',
            'proof' => 'شهادة اختبار جلدي و120 تقييماً بمتوسط 4.8', 'alternative' => 'كريمات الصيدلية العامة', 'goal' => '60 طلباً مؤهلاً خلال 30 يوماً', 'budget' => 3000, 'risk' => 'تكلفة الطلب تتجاوز 50 ريالاً',
            'benefit' => 'بشرة هادئة بلا تهيّج خلال 7 أيام', 'url' => 'https://example.com/serum', 'campaign' => 'إطلاق السيروم', 'medium' => 'cpc', 'content' => 'video-a', 'headline' => 'سيروم طبيعي للبشرة الحساسة',
            'promise' => 'بشرة هادئة بلا تهيّج خلال 7 أيام', 'cta' => 'اطلب الآن', 'fields_count' => 5, 'load' => 'medium', 'task' => 'ad', 'tone' => 'warm', 'constraints' => 'لا أرقام بلا مصدر، 60 كلمة كحد أقصى', 'weeks' => '4', 'posts_per_week' => 3, 'days' => '7',
        ]],
        ['name' => 'مثال: استشارات محاسبية للمنشآت الصغيرة', 'slug' => 'example-accounting', 'input' => [
            'offer' => 'باقة محاسبة شهرية للمنشآت الصغيرة تشمل الزكاة والضريبة', 'sector' => 'services', 'audience' => 'أصحاب المطاعم الصغيرة في جدة الذين يديرون الحسابات بأنفسهم', 'pain' => 'غرامات تأخير الإقرارات الضريبية وضياع الوقت في الفواتير',
            'trigger' => 'اقتراب موعد الإقرار الضريبي الفصلي', 'channel' => 'google', 'objection' => 'المحاسب الحر أرخص', 'differentiator' => 'محاسب مخصص يرد خلال ساعة مع ضمان عدم الغرامة',
            'proof' => '40 منشأة بلا غرامة واحدة خلال 2025', 'alternative' => 'محاسب حر بالساعة', 'goal' => '15 عقداً جديداً خلال 60 يوماً', 'budget' => 4000, 'risk' => 'طلبات من منشآت أكبر من المناسب للباقة',
            'benefit' => 'إقرارات في موعدها بلا غرامات', 'url' => 'https://example.com/accounting', 'campaign' => 'موسم الإقرار', 'medium' => 'cpc', 'content' => 'search-a', 'headline' => 'محاسبة شهرية للمطاعم الصغيرة',
            'promise' => 'إقرارات في موعدها بلا غرامات', 'cta' => 'احجز مكالمة 15 دقيقة', 'fields_count' => 3, 'load' => 'fast', 'task' => 'objection', 'tone' => 'expert', 'constraints' => 'بلا وعود قانونية مطلقة', 'weeks' => '2', 'posts_per_week' => 2, 'days' => '10',
        ]],
        ['name' => 'مثال: كافيه تخصصي', 'slug' => 'example-cafe', 'input' => [
            'offer' => 'اشتراك قهوة أسبوعي من محمصة محلية', 'sector' => 'food', 'audience' => 'موظفو الشركات في حي العليا الذين يشربون قهوة مختصة يومياً', 'pain' => 'الانتظار الطويل صباحاً وتفاوت جودة القهوة',
            'trigger' => 'عرض اشتراك أول أسبوع مجاناً', 'channel' => 'snapchat', 'objection' => 'لن ألتزم باشتراك', 'differentiator' => 'استلام في 60 ثانية من نقطة قريبة من المكتب وإلغاء بنقرة',
            'proof' => '300 مشترك نشط ومعدل تجديد 85٪', 'alternative' => 'قهوة السلاسل التجارية', 'goal' => '100 مشترك جديد خلال 45 يوماً', 'budget' => 2000, 'risk' => 'تكرار الإعلان على نفس الجمهور الصغير',
            'benefit' => 'قهوتك جاهزة في 60 ثانية كل صباح', 'url' => 'https://example.com/coffee', 'campaign' => 'اشتراك العليا', 'medium' => 'social', 'content' => 'story-a', 'headline' => 'قهوة مختصة باشتراك أسبوعي',
            'promise' => 'قهوتك جاهزة في 60 ثانية كل صباح', 'cta' => 'ابدأ أسبوعك المجاني', 'fields_count' => 2, 'load' => 'slow', 'task' => 'angles', 'tone' => 'bold', 'constraints' => 'لهجة قريبة من الشباب بلا مبالغة', 'weeks' => '4', 'posts_per_week' => 4, 'days' => '7',
        ]],
    ];

    public function run(): void
    {
        foreach (LabCatalog::tools() as $definition) {
            $tool = Tool::query()->updateOrCreate(['slug' => $definition['slug']], array_diff_key($definition, ['slug' => 1]));
            if ($tool->kind === 'generator' && ! $tool->versions()->exists()) {
                $tool->versions()->create([
                    'version' => 1, 'label' => 'القالب الأساسي', 'input_schema' => $tool->input_schema, 'output_schema' => ['markdown', 'quality'],
                    'output_template' => $tool->output_template, 'prompt_key' => 'marketing-ar-v1', 'ai_instructions' => 'حسّن الأصل التالي بلغة عربية دقيقة: أبقِ البنية والجداول، اجعل الجمل أكثر تحديداً، لا تخترع أرقاماً أو مصادر، واحتفظ بجمهور واحد وقناة واحدة.',
                    'config_hash' => hash('sha256', json_encode([$tool->input_schema, $tool->output_template], JSON_UNESCAPED_UNICODE)), 'is_active' => true,
                ]);
            }
        }

        $this->seedExamples();
    }

    /** Anonymous examples from other learners (illustrative): generated by the engine itself on three example projects. */
    private function seedExamples(): void
    {
        $demo = User::query()->firstOrCreate(['email' => 'demo@example.invalid'], ['name' => 'حساب العرض التعليمي', 'email_verified_at' => now(), 'password' => Hash::make(Str::random(40))]);
        $workspace = Workspace::query()->firstOrCreate(['slug' => 'illustrative-demo'], ['name' => 'مساحة العرض التعليمية']);
        $workspace->members()->syncWithoutDetaching([$demo->id => ['role' => 'owner']]);
        $engine = app(GeneratorEngine::class);
        foreach (self::EXAMPLE_PROJECTS as $example) {
            $project = $workspace->projects()->updateOrCreate(['slug' => $example['slug']], ['name' => $example['name'], 'profile' => ['illustrative' => true, 'offer' => $example['input']['offer'], 'sector' => $example['input']['sector'], 'audience' => $example['input']['audience'], 'channel' => $example['input']['channel'], 'budget' => $example['input']['budget']]]);
            foreach (Tool::query()->where('kind', 'generator')->where('is_published', true)->get() as $tool) {
                $generated = $engine->generate($tool, $example['input']);
                $existing = ToolOutput::query()->where('tool_id', $tool->id)->where('project_id', $project->id)->where('output_type', $tool->output_type)->first();
                $attributes = [
                    'title' => $generated['title'], 'format' => 'markdown', 'body_markdown' => $generated['markdown'], 'user_id' => $demo->id,
                    'payload' => ['input' => $generated['input'], 'illustrative' => true, 'example_project' => $example['name']], 'quality_report' => $generated['quality'],
                ];
                if ($existing) {
                    $existing->update($attributes);

                    continue;
                }
                $runId = \Illuminate\Support\Facades\DB::table('tool_runs')->insertGetId([
                    'workspace_id' => $workspace->id, 'project_id' => $project->id, 'tool_id' => $tool->id, 'tool_version_id' => $tool->activeVersion()?->id,
                    'user_id' => $demo->id, 'public_id' => (string) Str::uuid(), 'status' => 'completed',
                    'input_payload' => json_encode(['input' => $generated['input'], 'illustrative' => true], JSON_UNESCAPED_UNICODE),
                    'output_payload' => json_encode(['title' => $generated['title'], 'text' => $generated['markdown']], JSON_UNESCAPED_UNICODE),
                    'reserved_credits' => 0, 'used_credits' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
                ToolOutput::query()->create($attributes + ['tool_run_id' => $runId, 'tool_id' => $tool->id, 'tool_version_id' => $tool->activeVersion()?->id, 'version' => 1, 'output_type' => $tool->output_type, 'project_id' => $project->id]);
            }
        }
    }
}
