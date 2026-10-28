<?php

namespace Tests\Feature;

use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Services\AiGateway;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OperationalFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_an_isolated_workspace_project_and_welcome_credit(): void
    {
        $this->post('/register', [
            'name' => 'خالد', 'email' => 'owner@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $workspace = $user->workspaces()->firstOrFail();

        $this->assertSame('owner', $workspace->pivot->role);
        $this->assertDatabaseHas('projects', ['workspace_id' => $workspace->id, 'name' => 'مشروعي الأول']);
        $this->assertDatabaseHas('credit_entries', ['workspace_id' => $workspace->id, 'amount' => 20]);
    }

    public function test_member_can_run_free_campaign_brief_tool_and_save_output(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'مساحة خالد', 'slug' => 'khaled-space']);
        $workspace->members()->attach($user, ['role' => 'owner']);
        $project = $workspace->projects()->create(['name' => 'إطلاق جديد', 'slug' => 'launch']);
        $tool = Tool::query()->where('slug', 'campaign-brief-builder')->firstOrFail();

        $this->actingAs($user)->post(route('app.tools.run', $tool), [
            'workspace_id' => $workspace->id, 'project_id' => $project->id,
            'goal' => 'زيادة الطلبات', 'audience' => 'المتاجر الصغيرة', 'offer' => 'خدمة استشارة',
        ])->assertRedirect();

        $this->assertDatabaseHas('tool_runs', ['workspace_id' => $workspace->id, 'status' => 'completed']);
    }

    public function test_ai_tool_charges_credit_once_and_saves_completed_output(): void
    {
        $this->seed();
        config()->set('services.openai.key', 'test-key');
        Http::fake(['*/responses' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ثلاث زوايا محتوى عملية']]]], 'usage' => ['input_tokens' => 12, 'output_tokens' => 8]])]);
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'مساحة AI', 'slug' => 'ai-space']);
        $workspace->members()->attach($user, ['role' => 'owner']);
        $project = $workspace->projects()->create(['name' => 'محتوى', 'slug' => 'content']);
        app(CreditLedger::class)->record($workspace, 'grant:test', 'grant', 10, 'اختبار');
        $tool = Tool::query()->where('slug', 'content-angle-generator')->firstOrFail();

        $this->actingAs($user)->post(route('app.tools.run', $tool), [
            'workspace_id' => $workspace->id, 'project_id' => $project->id,
            'goal' => 'زيادة الوعي', 'audience' => 'أصحاب المتاجر', 'offer' => 'دليل مجاني',
        ])->assertRedirect();

        $this->assertSame(8, app(CreditLedger::class)->balance($workspace));
        $this->assertDatabaseHas('tool_runs', ['tool_id' => $tool->id, 'status' => 'completed', 'used_credits' => 2]);
    }

    public function test_unexpected_ai_failure_releases_reserved_credit_and_marks_run_failed(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'مساحة فشل', 'slug' => 'failed-ai-space']);
        $workspace->members()->attach($user, ['role' => 'owner']);
        $project = $workspace->projects()->create(['name' => 'اختبار فشل', 'slug' => 'failure']);
        app(CreditLedger::class)->record($workspace, 'grant:failure', 'grant', 10, 'اختبار');
        $tool = Tool::query()->where('slug', 'content-angle-generator')->firstOrFail();
        app()->instance(AiGateway::class, new class extends AiGateway
        {
            public function generate(string $input, ?string $instructions = null): array
            {
                throw new \RuntimeException('network down');
            }
        });

        $this->actingAs($user)->post(route('app.tools.run', $tool), [
            'workspace_id' => $workspace->id, 'project_id' => $project->id,
            'goal' => 'زيادة الوعي', 'audience' => 'أصحاب المتاجر', 'offer' => 'دليل مجاني',
        ])->assertRedirect()->assertSessionHasErrors('tool');

        $this->assertSame(10, app(CreditLedger::class)->balance($workspace));
        $this->assertDatabaseHas('tool_runs', ['tool_id' => $tool->id, 'status' => 'failed', 'reserved_credits' => 0]);
    }

    public function test_recovery_command_releases_stale_tool_reservations_idempotently(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'مساحة معلقة', 'slug' => 'stale-space']);
        $workspace->members()->attach($user, ['role' => 'owner']);
        $project = $workspace->projects()->create(['name' => 'معلق', 'slug' => 'stale']);
        $tool = Tool::query()->where('kind', 'ai')->firstOrFail();
        app(CreditLedger::class)->record($workspace, 'grant:stale', 'grant', 10, 'اختبار');
        app(CreditLedger::class)->record($workspace, 'run:stale-run:reserve', 'reserve', -2, 'حجز');
        DB::table('tool_runs')->insert([
            'workspace_id' => $workspace->id, 'project_id' => $project->id, 'tool_id' => $tool->id,
            'user_id' => $user->id, 'public_id' => 'stale-run', 'status' => 'running',
            'input_payload' => '{}', 'reserved_credits' => 2, 'used_credits' => 0,
            'created_at' => now()->subHour(), 'updated_at' => now()->subHour(),
        ]);

        $this->artisan('platform:recover-runs')->assertSuccessful();
        $this->artisan('platform:recover-runs')->assertSuccessful();

        $this->assertSame(10, app(CreditLedger::class)->balance($workspace));
        $this->assertDatabaseHas('tool_runs', ['public_id' => 'stale-run', 'status' => 'failed', 'reserved_credits' => 0]);
    }

    public function test_viewer_and_unpublished_tool_cannot_spend_workspace_credit(): void
    {
        $this->seed();
        $viewer = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'مساحة مقروءة', 'slug' => 'viewer-space']);
        $workspace->members()->attach($viewer, ['role' => 'viewer']);
        $project = $workspace->projects()->create(['name' => 'خاص', 'slug' => 'private']);
        $tool = Tool::query()->where('kind', 'ai')->firstOrFail();

        $payload = ['workspace_id' => $workspace->id, 'project_id' => $project->id, 'goal' => 'هدف', 'audience' => 'جمهور', 'offer' => 'عرض'];
        $this->actingAs($viewer)->post(route('app.tools.run', $tool), $payload)->assertForbidden();

        $workspace->members()->updateExistingPivot($viewer->id, ['role' => 'editor']);
        $tool->update(['is_published' => false]);
        $this->actingAs($viewer)->post(route('app.tools.run', $tool), $payload)->assertNotFound();
    }

    public function test_non_admin_cannot_open_operations_console(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_review_and_publish_original_content(): void
    {
        $admin = User::factory()->create(['platform_role' => 'admin']);
        $item = ContentItem::query()->create([
            'type' => 'article', 'status' => 'review', 'title' => 'مقال جديد', 'slug' => 'new-article',
            'excerpt' => 'ملخص واضح', 'body' => 'محتوى عملي', 'author_name' => 'فريق أثر',
        ]);

        $this->actingAs($admin)->get('/admin/content')->assertOk()->assertSee('مقال جديد');
        $this->actingAs($admin)->post('/admin/content/'.$item->id.'/publish')->assertRedirect();

        $this->assertSame('published', $item->fresh()->status);
    }

    public function test_platform_doctor_passes_in_testing_environment(): void
    {
        $this->artisan('platform:doctor --env=testing')->assertSuccessful();
    }
}
