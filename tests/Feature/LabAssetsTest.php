<?php

namespace Tests\Feature;

use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Lab\Models\PlanAssembly;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Lab\Services\LabCatalog;
use App\Domain\Lab\Services\QualityChecker;
use App\Domain\Lab\Services\TextDiff;
use App\Domain\Workspace\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** فحص قابلية التنفيذ الستّي، الإصدارات بفرق نصي، المشاركة برابط، وخطة المشروع المجمّعة (§4.7). */
class LabAssetsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Http::fake();
        $this->user = User::factory()->create(['audience' => 'owner']);
        $workspace = Workspace::query()->create(['name' => 'مساحة', 'slug' => 'space']);
        $workspace->members()->attach($this->user, ['role' => 'owner']);
        $this->project = $workspace->projects()->create(['name' => 'متجر نَدى', 'slug' => 'nada', 'profile' => ['offer' => 'منتجات عناية طبيعية', 'audience' => 'نساء 25–40 ذوات بشرة حساسة', 'channel' => 'instagram', 'budget' => 3000, 'goal' => 'leads', 'goal_number' => 60, 'sector' => 'beauty']]);
        app(CreditLedger::class)->record($workspace, 'grant:'.$this->user->id, 'grant', 20, 'رصيد');
    }

    private function generateAndSave(string $slug, array $input, ?string $markdownOverride = null): ToolOutput
    {
        $this->actingAs($this->user)->post("/tools/{$slug}/generate", $input + ['project_id' => $this->project->id])->assertRedirect();
        $result = session('lab.result.'.$slug);
        $this->actingAs($this->user)->post("/app/tools/{$slug}/save", ['project_id' => $this->project->id, 'title' => $result['title'], 'markdown' => $markdownOverride ?? $result['markdown']])->assertRedirect()->assertSessionHas('status');

        return ToolOutput::query()->where('user_id', $this->user->id)->where('output_type', LabCatalog::find($slug)['output_type'])->orderByDesc('version')->firstOrFail();
    }

    public function test_six_point_check_flags_what_is_missing_with_a_link_to_fix_it(): void
    {
        $this->post('/tools/campaign-brief-builder/generate', ['goal' => 'زيادة الطلبات', 'audience' => 'النساء، الرجال وكل الناس', 'offer' => 'سيروم', 'channel' => 'none', 'budget' => 300])->assertRedirect(route('tools.show', 'campaign-brief-builder').'#asset');
        $quality = session('lab.result.campaign-brief-builder')['quality'];
        $byKey = collect($quality['checks'])->keyBy('key');
        $this->assertSame(array_keys(QualityChecker::CHECKS), $byKey->keys()->all());
        $this->assertFalse($byKey['goal_number_date']['ok']);
        $this->assertSame('goal', $byKey['goal_number_date']['field']);
        $this->assertFalse($byKey['single_audience']['ok']);
        $this->assertFalse($byKey['single_channel']['ok']);
        $this->assertFalse($byKey['budget_consistency']['ok']);
        $this->assertTrue($byKey['metric_stop_rule']['ok']);
        $this->assertTrue($byKey['risk_mitigation']['ok']);
        $this->assertSame('outside', $quality['verdict']);
        $this->get('/tools/campaign-brief-builder')->assertOk()->assertSee('غير قابل للتنفيذ بعد')->assertSee('صحّحه')->assertSee('href="#f-goal"', false)->assertSee('اكتب الهدف برقم وتاريخ');

        $this->post('/tools/campaign-brief-builder/generate', LabCatalog::find('campaign-brief-builder')['example_input'])->assertRedirect();
        $quality = session('lab.result.campaign-brief-builder')['quality'];
        $this->assertSame(6, $quality['passed']);
        $this->assertSame('within', $quality['verdict']);
        $this->get('/tools/campaign-brief-builder')->assertOk()->assertSee('قابل للتنفيذ')->assertSee('6/6 بنود متحققة');
    }

    public function test_member_inputs_are_prefilled_from_the_project_profile_and_marked(): void
    {
        $this->actingAs($this->user)->get('/tools/campaign-brief-builder')->assertOk()->assertSee('من المشروع')->assertSee('value="نساء 25–40 ذوات بشرة حساسة"', false)->assertSee('value="3000"', false)->assertSee('60 طلبات أو محادثات مؤهلة خلال 30 يوماً')->assertSee('id="project_id"', false)->assertSee('حسّن بالذكاء الاصطناعي');
    }

    public function test_saving_creates_versions_that_never_overwrite_and_shows_a_text_diff(): void
    {
        $input = LabCatalog::find('value-proposition')['example_input'];
        $v1 = $this->generateAndSave('value-proposition', $input);
        $this->assertSame(1, $v1->version);
        $this->assertNull($v1->parent_output_id);
        $this->assertSame('value_proposition', $v1->output_type);
        $this->assertNotNull($v1->tool_version_id);
        $this->assertSame(6, count($v1->quality_report['checks']));

        $v2 = $this->generateAndSave('value-proposition', ['proof' => 'شهادة مختبر مستقل و300 تقييم'] + $input);
        $this->assertSame(2, $v2->version);
        $this->assertSame($v1->id, $v2->parent_output_id);
        $this->assertDatabaseHas('tool_outputs', ['id' => $v1->id, 'version' => 1]);
        $this->assertNotSame($v1->body_markdown, $v2->body_markdown);

        $diff = TextDiff::lines($v1->body_markdown, $v2->body_markdown);
        $this->assertNotEmpty($diff['added']);
        $this->assertNotEmpty($diff['removed']);
        $this->assertStringContainsString('300 تقييم', implode("\n", $diff['added']));

        $this->actingAs($this->user)->get(route('app.outputs.show', $v2))->assertOk()->assertSee('ما الذي تغيّر؟')->assertSee('300 تقييم')->assertSee('v2')->assertSee('v1')->assertSee('سطراً أُضيف');
        $this->actingAs($this->user)->get('/tools/value-proposition')->assertOk()->assertSee('data-versions', false)->assertSee('✓ محفوظ في مشروعي');
    }

    public function test_share_link_opens_a_public_read_page_and_revoking_it_returns_404(): void
    {
        // Saving and sharing need an account: a visitor is sent to the login page.
        $this->post('/app/tools/customer-persona/save', ['project_id' => $this->project->id, 'title' => 'x', 'markdown' => '# x'])->assertRedirect(route('login'));
        $output = $this->generateAndSave('customer-persona', LabCatalog::find('customer-persona')['example_input']);
        $this->assertNull($output->share_token);

        $this->actingAs($this->user)->post(route('app.outputs.share', $output))->assertRedirect()->assertSessionHas('share_url');
        $output->refresh();
        $this->assertNotNull($output->share_token);
        $url = route('assets.shared', $output->share_token);
        $this->assertSame(session('share_url'), $url);

        $this->flushSession();
        $this->get($url)->assertOk()->assertSee($output->title)->assertSee('أصل مشترك للقراءة')->assertSee('فحص قابلية التنفيذ')->assertHeader('X-Robots-Tag', 'noindex')->assertSee('name="robots" content="noindex', false)->assertDontSee('حفظ في مشروعي');

        $this->actingAs($this->user)->delete(route('app.outputs.unshare', $output))->assertRedirect();
        $this->assertNull($output->fresh()->share_token);
        $this->get($url)->assertNotFound();

        $other = User::factory()->create();
        $this->actingAs($other)->post(route('app.outputs.share', $output))->assertForbidden();
    }

    public function test_completing_the_ten_asset_chain_assembles_the_project_plan(): void
    {
        foreach (LabCatalog::generators() as $slug => $definition) {
            $this->generateAndSave($slug, $definition['example_input']);
        }
        $this->assertSame(10, ToolOutput::query()->where('user_id', $this->user->id)->count());
        $assembly = PlanAssembly::query()->where('project_id', $this->project->id)->firstOrFail();
        $this->assertCount(10, $assembly->outputs);
        $this->assertNotNull($assembly->assembled_at);

        $this->actingAs($this->user)->get(route('app.projects.show', $this->project))->assertOk()->assertSee('خطة مشروعك تتجمّع')->assertSee('افتح الخطة المجمّعة')->assertSee('10 من 10 أصول');
        $this->actingAs($this->user)->get(route('app.projects.plan', $this->project))->assertOk()->assertSee('خطة التسويق — متجر نَدى')->assertSee('السلسلة مكتملة')->assertSee('## شخصية العميل')->assertSee('## حزمة Prompts');
        $this->actingAs($this->user)->get('/tools/prompt-builder')->assertOk()->assertSee('اكتمال خطتك 100٪');
    }

    public function test_lesson_lab_assets_feed_the_tool_chain_and_tool_assets_feed_the_lesson(): void
    {
        $lesson = \App\Domain\Academy\Models\Lesson::query()->where('slug', 'first-segment')->firstOrFail();
        $this->actingAs($this->user)->post(route('academy.lesson.save', [$lesson->course, $lesson]), ['title' => 'ملف الشريحة الأولى', 'type' => 'segment_profile', 'markdown' => "# ملف الشريحة الأولى\n\nنساء 25–40"])->assertRedirect();
        $saved = ToolOutput::query()->where('output_type', 'segment_profile')->where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame($lesson->id, $saved->lesson_id);
        $this->assertSame(1, $saved->version);
        $page = $this->actingAs($this->user)->get('/tools/customer-persona')->assertOk();
        $this->assertStringContainsString('data-feed="segment_profile" data-ok="1"', $page->getContent());
        $this->actingAs($this->user)->get(route('app.outputs.index'))->assertOk()->assertSee('ملف الشريحة الأولى')->assertSee('درس: ');
    }
}
