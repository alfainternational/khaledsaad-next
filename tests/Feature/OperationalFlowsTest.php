<?php

namespace Tests\Feature;

use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Lab\Models\Tool;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_verified_payment_webhook_is_idempotent(): void
    {
        config()->set('services.payments.webhook_secret', 'secret');
        $workspace = Workspace::query()->create(['name' => 'مدفوع', 'slug' => 'paid']);
        $payload = ['event_id' => 'evt_100', 'type' => 'payment.succeeded', 'workspace_id' => $workspace->id, 'plan' => 'pro', 'credits' => 100];
        $body = json_encode($payload);
        $server = ['HTTP_X_PLATFORM_SIGNATURE' => hash_hmac('sha256', $body, 'secret'), 'CONTENT_TYPE' => 'application/json'];

        $this->call('POST', '/webhooks/payments', [], [], [], $server, $body)->assertOk();
        $this->call('POST', '/webhooks/payments', [], [], [], $server, $body)->assertOk();

        $this->assertDatabaseCount('webhook_events', 1);
        $this->assertDatabaseHas('subscriptions', ['workspace_id' => $workspace->id, 'plan' => 'pro', 'status' => 'active']);
        $this->assertSame(100, app(CreditLedger::class)->balance($workspace));
    }
}
