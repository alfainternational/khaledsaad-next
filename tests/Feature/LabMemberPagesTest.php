<?php

namespace Tests\Feature;

use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Lab\Services\AssetVault;
use App\Domain\Workspace\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** أصولي /app/outputs (§5.3) ومشروعي /app/projects/{project} (§5.4) وروابط الحساب. */
class LabMemberPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'مساحة', 'slug' => 'space']);
        $workspace->members()->attach($this->user, ['role' => 'owner']);
        $this->project = $workspace->projects()->create(['name' => 'مشروعي', 'slug' => 'mine']);
    }

    private function asset(string $slug, string $title, string $markdown): ToolOutput
    {
        $tool = Tool::query()->where('slug', $slug)->firstOrFail();

        return app(AssetVault::class)->save($this->user, $this->project, $tool, $title, $tool->output_type, $markdown, ['offer' => 'x']);
    }

    public function test_outputs_page_lists_filters_and_deletes_with_the_asset_page_showing_versions(): void
    {
        $persona = $this->asset('customer-persona', 'شخصية: نساء الرياض', "# شخصية\n\nنساء");
        $brief = $this->asset('campaign-brief-builder', 'موجز الحملة — سيروم', "# موجز\n\nهدف");
        $brief2 = $this->asset('campaign-brief-builder', 'موجز الحملة — سيروم', "# موجز\n\nهدف 60 طلباً خلال 30 يوماً");
        $this->assertSame(2, $brief2->version);

        $this->actingAs($this->user)->get('/app/outputs')->assertOk()->assertSee('أصولي')->assertSee('شخصية: نساء الرياض')->assertSee('موجز الحملة — سيروم')->assertSee('3 أصلاً')->assertSee('نسخ')->assertSee('PDF')->assertSee('حذف');
        $this->actingAs($this->user)->get('/app/outputs?type=customer_persona')->assertOk()->assertSee('1 أصلاً')->assertDontSee('موجز الحملة — سيروم');
        $this->actingAs($this->user)->get('/app/outputs?project='.$this->project->id)->assertOk()->assertSee('3 أصلاً');
        $this->actingAs($this->user)->get(route('app.outputs.show', $brief2))->assertOk()->assertSee('v2 من 2')->assertSee('ما الذي تغيّر؟')->assertSee('ولّد إصداراً جديداً');

        $other = User::factory()->create();
        $this->actingAs($other)->get(route('app.outputs.show', $persona))->assertForbidden();
        $this->actingAs($other)->delete(route('app.outputs.destroy', $persona))->assertForbidden();
        $this->actingAs($this->user)->delete(route('app.outputs.destroy', $persona))->assertRedirect(route('app.outputs.index'))->assertSessionHas('status');
        $this->assertDatabaseMissing('tool_outputs', ['id' => $persona->id]);
        $this->assertDatabaseHas('tool_outputs', ['id' => $brief->id]);
    }

    public function test_project_profile_is_editable_and_prefills_tools_with_assets_and_plan_box(): void
    {
        $this->actingAs($this->user)->get(route('app.projects.show', $this->project))->assertOk()->assertSee('ملف المشروع')->assertSee('خطة مشروعك تتجمّع')->assertSee('0 من 10 أصول')->assertSee('id="notes"', false);
        $this->actingAs($this->user)->patch(route('app.projects.update', $this->project), [
            'name' => 'متجر نَدى', 'offer' => 'منتجات عناية طبيعية', 'sector' => 'beauty', 'audience' => 'نساء 25–40 ذوات بشرة حساسة', 'goal' => 'leads', 'goal_number' => 60, 'channel' => 'instagram', 'budget' => 3000, 'city' => 'الرياض', 'notes' => 'نبدأ بإنستغرام فقط.',
        ])->assertRedirect()->assertSessionHas('status');
        $this->project->refresh();
        $this->assertSame('متجر نَدى', $this->project->name);
        $this->assertSame('instagram', $this->project->profile['channel']);
        $this->assertSame('نبدأ بإنستغرام فقط.', $this->project->profile['notes']);
        $this->actingAs($this->user)->patch(route('app.projects.update', $this->project), ['name' => 'متجر نَدى', 'channel' => 'nope'])->assertSessionHasErrors('channel');

        $this->actingAs($this->user)->get('/tools/customer-persona')->assertOk()->assertSee('من المشروع')->assertSee('value="منتجات عناية طبيعية"', false)->assertSee('ملف المشروع');
        $this->asset('customer-persona', 'شخصية', "# شخصية");
        $this->actingAs($this->user)->get(route('app.projects.show', $this->project))->assertOk()->assertSee('1 أصلاً')->assertSee('1 من 10 أصول')->assertSee('data-plan-item="customer_persona" data-state="saved"', false);
    }

    public function test_account_menu_and_dashboard_link_to_outputs_and_project(): void
    {
        $this->actingAs($this->user)->get('/dashboard')->assertOk()->assertSee(route('app.outputs.index'), false)->assertSee(route('app.projects.show', $this->project), false)->assertSee('أصولي')->assertSee('مشروعي');
        $this->actingAs($this->user)->get('/tools')->assertOk()->assertSee('href="'.route('app.outputs.index').'">أصولي', false)->assertSee('>مشروعي</a>', false);
    }
}
