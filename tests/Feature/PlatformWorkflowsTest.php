<?php

namespace Tests\Feature;

use App\Domain\Academy\Models\Course;
use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Lab\Models\Tool;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_update_and_delete_projects_in_their_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'العمل', 'slug' => 'work']);
        $workspace->members()->attach($user, ['role' => 'owner']);

        $this->actingAs($user)->post('/app/projects', [
            'workspace_id' => $workspace->id, 'name' => 'إطلاق المنتج',
        ])->assertRedirect();
        $project = $workspace->projects()->where('name', 'إطلاق المنتج')->firstOrFail();

        $this->actingAs($user)->patch(route('app.projects.update', $project), ['name' => 'إطلاق جديد'])->assertRedirect();
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'إطلاق جديد']);

        $this->actingAs($user)->delete(route('app.projects.destroy', $project))->assertRedirect('/dashboard');
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_verified_member_can_complete_a_published_lesson(): void
    {
        $user = User::factory()->create();
        $course = Course::query()->create(['title' => 'دورة', 'slug' => 'workflow-course', 'summary' => 'ملخص', 'is_published' => true]);
        $lesson = $course->lessons()->create(['title' => 'درس', 'slug' => 'workflow-lesson', 'objective' => 'هدف', 'content' => 'محتوى']);

        $this->actingAs($user)->post(route('app.lessons.complete', $lesson), ['deliverable' => 'طبقت التمرين'])->assertRedirect();

        $this->assertDatabaseHas('activity_completions', ['user_id' => $user->id, 'lesson_id' => $lesson->id]);
    }

    public function test_project_page_lists_saved_tool_outputs_and_member_can_remove_one(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'المخرجات', 'slug' => 'outputs']);
        $workspace->members()->attach($user, ['role' => 'editor']);
        $project = $workspace->projects()->create(['name' => 'مشروع', 'slug' => 'project']);
        $tool = Tool::query()->where('slug', 'campaign-brief-builder')->firstOrFail();
        $output = app(\App\Domain\Lab\Services\AssetVault::class)->save($user, $project, $tool, 'مخرج محفوظ', 'campaign_brief', "# مخرج محفوظ

نص", ['offer' => 'x']);

        $this->actingAs($user)->get(route('app.projects.show', $project))->assertOk()->assertSee('مخرج محفوظ')->assertSee('أصول المشروع');
        $this->actingAs($user)->delete(route('app.outputs.destroy', $output))->assertRedirect();
        $this->assertDatabaseMissing('tool_outputs', ['id' => $output->id]);
    }

    public function test_billing_page_shows_balance_and_subscription(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'فوترة', 'slug' => 'billing']);
        $workspace->members()->attach($user, ['role' => 'owner']);
        app(CreditLedger::class)->record($workspace, 'grant:billing', 'grant', 25, 'رصيد');
        DB::table('subscriptions')->insert([
            'workspace_id' => $workspace->id, 'plan' => 'pro', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->get('/app/billing')->assertOk()->assertSee('25')->assertSee('pro');
    }

    public function test_admin_can_manage_redirects_and_view_commerce_operations(): void
    {
        $admin = User::factory()->create(['platform_role' => 'admin']);

        $this->actingAs($admin)->post('/admin/redirects', [
            'from_path' => '/legacy', 'to_path' => '/articles', 'status_code' => 301,
        ])->assertRedirect();

        $this->actingAs($admin)->get('/admin/operations')->assertOk()->assertSee('/legacy')->assertSee('المدفوعات');
    }

    public function test_editor_cannot_change_prices_and_admin_change_is_audited(): void
    {
        $editor = User::factory()->create(['platform_role' => 'editor']);
        $admin = User::factory()->create(['platform_role' => 'admin']);
        DB::table('plans')->insert([
            'slug' => 'pro', 'name' => 'احترافية', 'price' => 4900, 'currency' => 'SAR',
            'credits' => 100, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($editor)->patch('/admin/plans/pro', ['price' => 9900, 'credits' => 200])->assertForbidden();
        $this->actingAs($admin)->patch('/admin/plans/pro', ['price' => 9900, 'credits' => 200])->assertRedirect();

        $this->assertDatabaseHas('plans', ['slug' => 'pro', 'price' => 9900, 'credits' => 200]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.price_changed']);
    }
}
