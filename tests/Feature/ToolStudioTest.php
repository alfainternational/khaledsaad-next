<?php

namespace Tests\Feature;

use App\Domain\Lab\Models\Experiment;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Lab\Services\LabCatalog;
use App\Domain\Lab\Services\StudioMetrics;
use App\Domain\Workspace\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** استوديو الأداة /admin/tools (§6.4): المخطط، القالب، الإصدارات مع A/B، الاختبار النموذجي، معدل الحفظ، والتدقيق. */
class ToolStudioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $member;

    private Project $project;

    private Tool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create(['platform_role' => 'admin']);
        $this->member = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'مساحة', 'slug' => 'space']);
        $workspace->members()->attach($this->member, ['role' => 'owner']);
        $this->project = $workspace->projects()->create(['name' => 'مشروع', 'slug' => 'p']);
        $this->tool = Tool::query()->where('slug', 'campaign-brief-builder')->firstOrFail();
    }

    public function test_studio_is_for_editors_only_and_lists_tools_with_save_rate(): void
    {
        $this->actingAs($this->member)->get('/admin/tools')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/tools')->assertOk()->assertSee('استوديو الأدوات')->assertSee('معدل الحفظ')->assertSee('موجز الحملة')->assertSee('حاسبة CAC')->assertSee('محاكي الحملة الإعلانية')->assertDontSee('مختبر الدرس');
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('لا تجربة A/B نشطة')->assertSee(route('admin.tools.index'), false)->assertDontSee('A/B معطّل')->assertDontSee('جداول التجارب في المرحلة 2');
    }

    public function test_save_rate_is_saved_over_generated_per_tool_and_per_version(): void
    {
        $input = LabCatalog::find('campaign-brief-builder')['example_input'];
        $this->actingAs($this->member)->post('/tools/campaign-brief-builder/generate', $input)->assertRedirect();
        $this->actingAs($this->member)->post('/tools/campaign-brief-builder/generate', $input)->assertRedirect();
        $result = session('lab.result.campaign-brief-builder');
        $this->actingAs($this->member)->post('/app/tools/campaign-brief-builder/save', ['project_id' => $this->project->id, 'title' => $result['title'], 'markdown' => $result['markdown']])->assertRedirect();

        $rates = app(StudioMetrics::class)->saveRates();
        $this->assertSame(['generated' => 2, 'saved' => 1, 'rate' => 50], $rates[$this->tool->id]);
        $version = $this->tool->activeVersion();
        $this->assertSame(50, app(StudioMetrics::class)->versionRates($this->tool->fresh()->load('versions'))[$version->id]['rate']);
        $this->actingAs($this->admin)->get(route('admin.tools.show', $this->tool))->assertOk()->assertSee('50٪')->assertSee('1 محفوظ من 2 مولَّد');
        $this->actingAs($this->admin)->get('/admin/tools')->assertOk()->assertSee('50٪');
    }

    public function test_editor_updates_schema_template_and_rules_and_the_public_page_follows_with_an_audit_row(): void
    {
        $schema = $this->tool->input_schema;
        $schema[] = ['key' => 'deadline', 'label' => 'موعد الإطلاق', 'type' => 'text', 'required' => false];
        $this->actingAs($this->admin)->put(route('admin.tools.update', $this->tool), [
            'name' => 'موجز الحملة', 'description' => $this->tool->description, 'tagline' => $this->tool->tagline, 'category' => 'التخطيط', 'credit_cost' => 1, 'is_published' => 1,
            'input_schema' => json_encode($schema, JSON_UNESCAPED_UNICODE), 'output_template' => "# موجز مختصر — {{offer_short}}\n\nالهدف: {{goal}}\nالموعد: {{deadline}}",
            'required_rules' => ['goal_number_date'], 'advisory_rules' => ['single_audience'], 'feeds_from' => ['profile', 'customer_persona'], 'feeds_into' => ['utm_links'],
        ])->assertRedirect()->assertSessionHas('status');
        $this->tool->refresh();
        $this->assertSame(1, $this->tool->credit_cost);
        $this->assertSame(['goal_number_date'], $this->tool->quality_rules['required']);
        $this->assertSame(['utm_links'], $this->tool->feeds_into);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tool.updated', 'subject_type' => 'tool', 'subject_id' => (string) $this->tool->id, 'user_id' => $this->admin->id]);

        // The live definition is what the public tool renders when no version template overrides it.
        $this->tool->versions()->update(['output_template' => null]);
        $this->get('/tools/campaign-brief-builder')->assertOk()->assertSee('موعد الإطلاق');
        $this->post('/tools/campaign-brief-builder/generate', LabCatalog::find('campaign-brief-builder')['example_input'] + ['deadline' => '2026/12/01'])->assertRedirect();
        $this->assertStringContainsString('الموعد: 2026/12/01', session('lab.result.campaign-brief-builder')['markdown']);
        $this->assertSame('لا ينطبق على هذه الأداة.', collect(session('lab.result.campaign-brief-builder')['quality']['checks'])->firstWhere('key', 'single_channel')['hint']);

        $this->actingAs($this->admin)->put(route('admin.tools.update', $this->tool), ['name' => 'x', 'description' => 'y', 'category' => 'التخطيط', 'credit_cost' => 0, 'input_schema' => '{"bad":1}'])->assertSessionHasErrors('input_schema');
    }

    public function test_versions_ab_experiment_exposures_conversions_and_winner_adoption(): void
    {
        $this->actingAs($this->admin)->post(route('admin.tools.versions.store', $this->tool), ['label' => 'جدول مختصر', 'output_template' => "# موجز الحملة B — {{offer_short}}\n\n{{goal}}", 'ai_instructions' => 'اختصر.'])->assertRedirect()->assertSessionHas('status');
        $this->tool->load('versions');
        $this->assertSame(2, $this->tool->versions->count());
        $v1 = $this->tool->versions->firstWhere('version', 1);
        $v2 = $this->tool->versions->firstWhere('version', 2);
        $this->assertTrue($v1->is_active);
        $this->assertFalse($v2->is_active);

        $this->actingAs($this->admin)->post(route('admin.tools.experiment.start', $this->tool), ['variant_a' => $v1->id, 'variant_b' => $v2->id, 'weight_a' => 50, 'name' => 'جدول مقابل قائمة'])->assertRedirect()->assertSessionHas('status');
        $experiment = Experiment::query()->where('subject_id', $this->tool->id)->where('status', 'running')->firstOrFail();
        $this->assertSame(2, $experiment->variants()->count());
        $this->actingAs($this->admin)->post(route('admin.tools.experiment.start', $this->tool), ['variant_a' => $v1->id, 'variant_b' => $v2->id, 'weight_a' => 50])->assertSessionHasErrors('experiment');
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('جدول مقابل قائمة')->assertDontSee('لا تجربة A/B نشطة');

        // A learner is assigned a variant once per session; generating exposes, saving converts.
        $input = LabCatalog::find('campaign-brief-builder')['example_input'];
        $this->actingAs($this->member)->post('/tools/campaign-brief-builder/generate', $input)->assertRedirect();
        $assigned = (int) session('lab.exp.'.$experiment->id);
        $this->assertContains($assigned, $experiment->variants->pluck('id')->all());
        $this->actingAs($this->member)->post('/tools/campaign-brief-builder/generate', $input)->assertRedirect();
        $this->assertSame($assigned, (int) session('lab.exp.'.$experiment->id));
        $this->assertSame(2, DB::table('experiment_exposures')->where('experiment_id', $experiment->id)->where('variant_id', $assigned)->count());
        $result = session('lab.result.campaign-brief-builder');
        $usedVersion = $experiment->variants->firstWhere('id', $assigned)->tool_version_id;
        $this->assertSame($usedVersion, $result['version_id']);
        if ($usedVersion === $v2->id) {
            $this->assertStringStartsWith('# موجز الحملة B', $result['markdown']);
        }
        $this->actingAs($this->member)->post('/app/tools/campaign-brief-builder/save', ['project_id' => $this->project->id, 'title' => $result['title'], 'markdown' => $result['markdown']])->assertRedirect();
        $this->assertSame(1, DB::table('experiment_exposures')->where('experiment_id', $experiment->id)->whereNotNull('converted_at')->count());
        $this->assertSame($usedVersion, ToolOutput::query()->where('user_id', $this->member->id)->firstOrFail()->tool_version_id);
        $rates = app(StudioMetrics::class)->experimentRates($experiment->load('variants'));
        $this->assertSame(['exposed' => 2, 'converted' => 1, 'rate' => 50], $rates[$assigned]);
        $this->actingAs($this->admin)->get(route('admin.tools.show', $this->tool))->assertOk()->assertSee('قيد التشغيل')->assertSee('اعتمد الفائز');

        $winner = $experiment->variants->firstWhere('tool_version_id', $v2->id);
        $this->actingAs($this->admin)->post(route('admin.tools.experiment.end', [$this->tool, $experiment]), ['winner' => $winner->id])->assertRedirect();
        $this->assertSame('ended', $experiment->fresh()->status);
        $this->assertTrue($v2->fresh()->is_active);
        $this->assertFalse($v1->fresh()->is_active);
        $this->assertStringStartsWith('# موجز الحملة B', $this->tool->fresh()->output_template);
        foreach (['tool.version_created', 'tool.experiment_started', 'tool.experiment_ended'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action, 'subject_id' => (string) $this->tool->id]);
        }
    }

    public function test_studio_test_run_renders_the_asset_and_six_checks_without_touching_learner_data(): void
    {
        $input = LabCatalog::find('campaign-brief-builder')['example_input'];
        $this->actingAs($this->admin)->post(route('admin.tools.test', $this->tool), $input + ['version_id' => 0])->assertRedirect(route('admin.tools.show', $this->tool).'#test')->assertSessionHas('status');
        $page = $this->actingAs($this->admin)->get(route('admin.tools.show', $this->tool))->assertOk()->assertSee('مخرج الاختبار')->assertSee('data-test-result', false)->assertSee('فحص قابلية التنفيذ')->assertSee('اختبر الآن');
        $this->assertSame(6, substr_count($page->getContent(), 'data-check="'));
        $this->assertDatabaseMissing('learner_events', ['event' => 'tool.generated']);
        $this->assertSame(0, ToolOutput::query()->where('payload', 'not like', '%"illustrative":true%')->count());
        $this->actingAs($this->member)->post(route('admin.tools.test', $this->tool), $input)->assertForbidden();
    }
}
