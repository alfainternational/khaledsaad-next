<?php

namespace Tests\Feature;

use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** الشاشات القياسية (§6.5): المستخدمون والأدوار، الخطط والدفع، التحويلات (+CSV)، سجل التدقيق، والتشغيل على قالب المنصة. */
class AdminStandardScreensTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'member', array $attributes = []): User
    {
        return User::factory()->create(['platform_role' => $role] + $attributes);
    }

    public function test_each_screen_answers_by_role(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
        $matrix = [
            '/admin/users' => ['ok' => ['operations', 'admin', 'super_admin'], 'forbidden' => ['member', 'editor', 'finance']],
            '/admin/plans' => ['ok' => ['finance', 'admin', 'super_admin'], 'forbidden' => ['member', 'editor', 'operations']],
            '/admin/redirects' => ['ok' => ['operations', 'admin', 'super_admin'], 'forbidden' => ['member', 'editor', 'finance']],
            '/admin/audit' => ['ok' => ['operations', 'admin', 'super_admin'], 'forbidden' => ['member', 'editor', 'finance']],
            '/admin/operations' => ['ok' => ['operations', 'admin', 'super_admin'], 'forbidden' => ['member', 'editor', 'finance']],
        ];
        foreach ($matrix as $path => $roles) {
            foreach ($roles['ok'] as $role) {
                $this->actingAs($this->user($role))->get($path)->assertOk()->assertSee('admin-nav', false);
            }
            foreach ($roles['forbidden'] as $role) {
                $this->actingAs($this->user($role))->get($path)->assertForbidden();
            }
        }
    }

    public function test_users_table_searches_sorts_and_lists_workspaces(): void
    {
        $admin = $this->user('admin', ['name' => 'المدير']);
        $member = $this->user('member', ['name' => 'سارة التاجر', 'email' => 'sara@example.com', 'audience' => 'owner']);
        $workspace = Workspace::query()->create(['name' => 'مساحة سارة', 'slug' => 'sara-space']);
        $workspace->members()->attach($member, ['role' => 'owner']);
        $this->user('member', ['name' => 'آخر', 'email' => 'other@example.com']);

        $this->actingAs($admin)->get('/admin/users?q=sara')->assertOk()
            ->assertSee('سارة التاجر')->assertSee('sara@example.com')->assertSee('صاحب مشروع')->assertDontSee('other@example.com')
            ->assertSee('اعرض كهذا المستخدم')->assertSee('aria-sort="none"', false);
        $this->actingAs($admin)->get('/admin/users?sort=name&dir=asc')->assertOk()->assertSee('aria-sort="ascending"', false);
        $this->actingAs($admin)->get('/admin/users?sort=password&dir=asc')->assertOk();
    }

    public function test_role_change_rules_and_audit_row(): void
    {
        $admin = $this->user('admin');
        $super = $this->user('super_admin');
        $member = $this->user('member');

        // Nobody changes their own role.
        $this->actingAs($admin)->patch('/admin/users/'.$admin->id, ['platform_role' => 'super_admin'])->assertForbidden();
        $this->actingAs($super)->patch('/admin/users/'.$super->id, ['platform_role' => 'admin'])->assertForbidden();
        // Only super_admin grants or removes super_admin.
        $this->actingAs($admin)->patch('/admin/users/'.$member->id, ['platform_role' => 'super_admin'])->assertForbidden();
        $this->actingAs($admin)->patch('/admin/users/'.$super->id, ['platform_role' => 'member'])->assertForbidden();
        // Staff below admin cannot change roles at all; invalid role rejected.
        $this->actingAs($this->user('operations'))->patch('/admin/users/'.$member->id, ['platform_role' => 'editor'])->assertForbidden();
        $this->actingAs($admin)->patch('/admin/users/'.$member->id, ['platform_role' => 'owner'])->assertSessionHasErrors('platform_role');
        $this->assertSame('member', $member->fresh()->platform_role);

        $this->actingAs($admin)->from('/admin/users')->patch('/admin/users/'.$member->id, ['platform_role' => 'editor'])->assertRedirect('/admin/users');
        $this->assertSame('editor', $member->fresh()->platform_role);
        $row = DB::table('audit_logs')->where('action', 'user.role_changed')->where('subject_id', (string) $member->id)->first();
        $this->assertNotNull($row);
        $this->assertSame($admin->id, (int) $row->user_id);
        $this->assertSame('user', $row->subject_type);
        $context = json_decode($row->context, true);
        $this->assertSame(['before' => 'member', 'after' => 'editor'], ['before' => $context['before'], 'after' => $context['after']]);
        $this->assertNotEmpty($row->request_id);

        $this->actingAs($super)->patch('/admin/users/'.$member->id, ['platform_role' => 'super_admin'])->assertRedirect();
        $this->assertSame('super_admin', $member->fresh()->platform_role);
    }

    public function test_impersonation_start_guard_banner_and_stop(): void
    {
        $admin = $this->user('admin', ['name' => 'المدير']);
        $member = $this->user('member', ['name' => 'عضو تجريبي']);
        $workspace = Workspace::query()->create(['name' => 'مساحة', 'slug' => 'space']);
        $workspace->members()->attach($member, ['role' => 'owner']);

        // Staff and self cannot be impersonated.
        $this->actingAs($admin)->post('/admin/users/'.$this->user('editor')->id.'/impersonate')->assertForbidden();
        $this->actingAs($admin)->post('/admin/users/'.$admin->id.'/impersonate')->assertForbidden();
        $this->actingAs($this->user('operations'))->post('/admin/users/'.$member->id.'/impersonate')->assertForbidden();

        $this->actingAs($admin)->post('/admin/users/'.$member->id.'/impersonate')->assertRedirect('/dashboard');
        $this->assertSame($member->id, auth()->id());
        $this->assertSame($admin->id, session('impersonator_id'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.impersonation_started', 'user_id' => $admin->id, 'subject_id' => (string) $member->id]);

        $this->get('/')->assertOk()->assertSee('تعرض المنصة الآن كالمستخدم «عضو تجريبي»')->assertSee(route('admin.impersonation.stop'), false);

        // The guard blocks spending, credential changes and the admin area while impersonating.
        $this->post('/app/billing/orders', ['plan' => 'pro'])->assertForbidden();
        $this->patch('/profile', ['name' => 'x', 'email' => 'x@example.com'])->assertForbidden();
        $this->delete('/profile', ['password' => 'password'])->assertForbidden();
        $this->put('/password', ['current_password' => 'password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertForbidden();
        $this->post('/admin/preview/visitor')->assertForbidden();
        $this->post('/admin/users/'.$member->id.'/impersonate')->assertForbidden();
        $this->assertSame('عضو تجريبي', $member->fresh()->name);
        // Reading stays possible.
        $this->get('/profile')->assertOk();

        $this->post('/admin/impersonation/stop')->assertRedirect('/admin/users');
        $this->assertSame($admin->id, auth()->id());
        $this->assertNull(session('impersonator_id'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.impersonation_ended', 'user_id' => $admin->id, 'subject_id' => (string) $member->id]);
        $this->get('/')->assertOk()->assertDontSee('تعرض المنصة الآن كالمستخدم');
        $this->get('/admin/users')->assertOk();

        // Stop without an active impersonation is refused.
        $this->post('/admin/impersonation/stop')->assertForbidden();
    }

    public function test_redirects_csv_import_reports_counts_and_rejects_bad_rows(): void
    {
        $ops = $this->user('operations');
        DB::table('redirects')->insert(['from_path' => '/blog', 'to_path' => '/old-target', 'status_code' => 302, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $csv = UploadedFile::fake()->createWithContent('redirects.csv', implode("\n", [
            'from_path,to_path,status_code',
            '/blog,/articles,301',
            '/download/android,,410',
            '/evil,//evil.com,301',
            '/services,/tools,308',
        ]));

        $this->actingAs($ops)->post('/admin/redirects/import', ['csv' => $csv])->assertRedirect('/admin/redirects')
            ->assertSessionHas('info', 'استُوردت 2، حُدّثت 1، رُفضت 1 (الأسطر: 4).');
        $this->assertDatabaseCount('redirects', 3);
        $this->assertDatabaseHas('redirects', ['from_path' => '/blog', 'to_path' => '/articles', 'status_code' => 301]);
        $this->assertDatabaseHas('redirects', ['from_path' => '/download/android', 'to_path' => '/', 'status_code' => 410]);
        $this->assertDatabaseHas('redirects', ['from_path' => '/services', 'to_path' => '/tools', 'status_code' => 308]);
        $this->assertDatabaseMissing('redirects', ['from_path' => '/evil']);
        $row = DB::table('audit_logs')->where('action', 'redirect.imported')->first();
        $this->assertNotNull($row);
        $this->assertSame(['inserted' => 2, 'updated' => 1, 'rejected' => [4]], json_decode($row->context, true));
        $this->get('/blog')->assertRedirect('/articles');
        $this->get('/download/android')->assertStatus(410);

        $this->actingAs($ops)->post('/admin/redirects/import', ['csv' => UploadedFile::fake()->create('big.csv', 600, 'text/csv')])->assertSessionHasErrors('csv');
        $this->actingAs($ops)->post('/admin/redirects/import', [])->assertSessionHasErrors('csv');
        $this->actingAs($this->user('finance'))->post('/admin/redirects/import', ['csv' => $csv])->assertForbidden();
    }

    public function test_redirects_screen_lists_adds_and_deletes_with_audit(): void
    {
        $ops = $this->user('operations');
        $this->actingAs($ops)->post('/admin/redirects', ['from_path' => '/old', 'to_path' => '/new', 'status_code' => 301])->assertRedirect();
        $id = DB::table('redirects')->where('from_path', '/old')->value('id');
        $this->actingAs($ops)->get('/admin/redirects?q=old')->assertOk()->assertSee('/old')->assertSee('/new')->assertSee('data-confirm', false)->assertSee('استيراد CSV');

        $this->actingAs($this->user('editor'))->delete('/admin/redirects/'.$id)->assertForbidden();
        $this->actingAs($ops)->delete('/admin/redirects/'.$id)->assertRedirect();
        $this->assertDatabaseMissing('redirects', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'redirect.deleted', 'subject_id' => (string) $id]);
        $this->actingAs($ops)->delete('/admin/redirects/'.$id)->assertNotFound();
    }

    public function test_plans_screen_updates_price_credits_and_active_flag_with_audit(): void
    {
        $this->seed();
        $finance = $this->user('finance');
        $plan = DB::table('plans')->where('slug', 'pro')->first();
        $this->assertNotNull($plan);

        $this->actingAs($finance)->get('/admin/plans')->assertOk()->assertSee($plan->name)->assertSee('آخر 50 دفعة')->assertSee('الاشتراكات النشطة')->assertSee('name="is_active"', false);

        $this->actingAs($finance)->patch('/admin/plans/pro', ['price' => 19900, 'credits' => 120])->assertRedirect();
        $fresh = DB::table('plans')->where('slug', 'pro')->first();
        $this->assertSame(19900, (int) $fresh->price);
        $this->assertSame(120, (int) $fresh->credits);
        $this->assertFalse((bool) $fresh->is_active);

        $this->actingAs($finance)->patch('/admin/plans/pro', ['price' => 19900, 'credits' => 120, 'is_active' => 1])->assertRedirect();
        $this->assertTrue((bool) DB::table('plans')->where('slug', 'pro')->value('is_active'));
        $rows = DB::table('audit_logs')->where('action', 'plan.price_changed')->where('subject_id', (string) $plan->id)->get();
        $this->assertCount(2, $rows);
        $context = json_decode($rows->last()->context, true);
        $this->assertFalse($context['before']['is_active']);
        $this->assertTrue($context['after']['is_active']);
        $this->assertSame(19900, $context['after']['price']);

        $this->actingAs($finance)->patch('/admin/plans/pro', ['price' => -1, 'credits' => 1])->assertSessionHasErrors('price');
        $this->actingAs($finance)->patch('/admin/plans/missing', ['price' => 1, 'credits' => 1])->assertNotFound();
        $this->actingAs($this->user('operations'))->patch('/admin/plans/pro', ['price' => 1, 'credits' => 1])->assertForbidden();
    }

    public function test_audit_screen_filters_by_action_prefix_user_and_dates(): void
    {
        $admin = $this->user('admin', ['email' => 'admin@example.com']);
        $other = $this->user('operations', ['email' => 'ops@example.com']);
        $insert = fn (int $userId, string $action, string $when, array $context = []) => DB::table('audit_logs')->insert([
            'user_id' => $userId, 'action' => $action, 'subject_type' => 'redirect', 'subject_id' => $action,
            'context' => json_encode($context, JSON_UNESCAPED_UNICODE), 'request_id' => null, 'created_at' => $when, 'updated_at' => $when,
        ]);
        // Context markers identify rows (action names also appear in the filter datalist).
        $insert($admin->id, 'redirect.created', '2026-10-01 10:00:00', ['marker' => 'MARK-CREATED', 'to' => '/articles']);
        $insert($admin->id, 'redirect.deleted', '2026-10-15 10:00:00', ['marker' => 'MARK-DELETED']);
        $insert($other->id, 'plan.price_changed', '2026-10-20 10:00:00', ['marker' => 'MARK-PLAN']);

        $this->actingAs($admin)->get('/admin/audit')->assertOk()->assertSee('MARK-CREATED')->assertSee('MARK-DELETED')->assertSee('MARK-PLAN')->assertSee('&quot;/articles&quot;', false)->assertSee('admin@example.com')->assertSee('ops@example.com');
        $this->actingAs($admin)->get('/admin/audit?action=redirect.')->assertOk()->assertSee('MARK-CREATED')->assertSee('MARK-DELETED')->assertDontSee('MARK-PLAN');
        $this->actingAs($admin)->get('/admin/audit?action=redirect.deleted')->assertOk()->assertSee('MARK-DELETED')->assertDontSee('MARK-CREATED');
        $this->actingAs($admin)->get('/admin/audit?user=ops@example.com')->assertOk()->assertSee('MARK-PLAN')->assertDontSee('MARK-CREATED');
        $this->actingAs($admin)->get('/admin/audit?user='.$admin->id)->assertOk()->assertSee('MARK-CREATED')->assertDontSee('MARK-PLAN');
        $this->actingAs($admin)->get('/admin/audit?from=2026-10-10&to=2026-10-16')->assertOk()->assertSee('MARK-DELETED')->assertDontSee('MARK-CREATED')->assertDontSee('MARK-PLAN');
        $this->actingAs($admin)->get('/admin/audit?from=not-a-date')->assertSessionHasErrors('from');
        $this->actingAs($this->user('editor'))->get('/admin/audit')->assertForbidden();
    }

    public function test_operations_page_renders_on_the_platform_layout_with_health_cards(): void
    {
        $this->seed();
        cache()->put('platform:scheduler_heartbeat', now()->timestamp - 30);
        cache()->put('platform:worker_heartbeat', now()->timestamp - 1200);
        $this->actingAs($this->user('operations'))->get('/admin/operations')->assertOk()
            ->assertSee('admin-nav', false)->assertDontSee('x-app-layout')->assertDontSee('max-w-7xl')
            ->assertSee('العمليات والصحة')->assertSee('قاعدة البيانات')->assertSee('متصلة')->assertSee('المجدول')->assertSee('آخر نبض قبل 30 ث')->assertSee('متأخر 1200 ث')
            ->assertSee('مهام معلّقة في الطابور')->assertSee('مهام فاشلة')->assertSee('تشغيلات أدوات فاشلة')
            ->assertSee(route('admin.users.index'), false)->assertSee(route('admin.plans.index'), false)->assertSee(route('admin.redirects.index'), false)->assertSee(route('admin.audit.index'), false)
            ->assertDontSee('name="from_path"', false)->assertDontSee('name="credits"', false);
        $this->actingAs($this->user('admin'))->get('/admin')->assertOk()->assertSee(route('admin.users.index'), false)->assertSee(route('admin.audit.index'), false);
    }
}
