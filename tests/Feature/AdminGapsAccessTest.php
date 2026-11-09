<?php

namespace Tests\Feature;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Insights\Models\ContentReport;
use App\Domain\Lab\Models\Tool;
use App\Http\Middleware\EnsureStaff;
use App\Models\User;
use App\Support\Viewer;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** وسيط staff على مجموعة /admin (§6.5): الزائر إلى /login، العضو 403 على كل رابط، والموظف يمرّ ثم يحكمه فحص الدور داخل كل متحكم. والشريط الجانبي من مكان واحد. */
class AdminGapsAccessTest extends TestCase
{
    use RefreshDatabase;

    private const GET_URLS = [
        '/admin', '/admin/academy', '/admin/tools', '/admin/assessment', '/admin/content', '/admin/content/create', '/admin/content/tags',
        '/admin/sources', '/admin/operations', '/admin/users', '/admin/plans', '/admin/redirects', '/admin/audit', '/admin/contact-messages',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function user(string $role = 'member'): User
    {
        return User::factory()->create(['platform_role' => $role]);
    }

    public function test_every_admin_route_sits_behind_the_staff_middleware(): void
    {
        // The HTTP kernel hands the middleware aliases and groups to the router when it is built.
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $admin = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => $route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/'));
        $this->assertGreaterThan(50, $admin->count());
        foreach ($admin as $route) {
            // Resolved classes after aliases and withoutMiddleware() exclusions: what actually runs for the route.
            $middleware = array_values(array_filter(app('router')->gatherRouteMiddleware($route), 'is_string'));
            $this->assertNotEmpty(array_filter($middleware, fn (string $m) => is_a(explode(':', $m)[0], AuthenticatesRequests::class, true)), $route->uri().' is not behind auth: '.implode(', ', $middleware));
            if ($route->getName() === 'admin.impersonation.stop') {
                // The impersonated member's session must be able to end the impersonation.
                $this->assertNotContains(EnsureStaff::class, $middleware);

                continue;
            }
            $this->assertContains(EnsureStaff::class, $middleware, $route->uri().' is not behind the staff middleware');
        }
        $this->assertSame(EnsureStaff::class, app('router')->getMiddleware()['staff']);
    }

    public function test_guest_is_redirected_to_login_on_admin_urls(): void
    {
        foreach (self::GET_URLS as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post('/admin/nudges', ['user_id' => 1, 'lesson_id' => 1])->assertRedirect('/login');
        $this->patch('/admin/reports/1', ['status' => 'resolved'])->assertRedirect('/login');
        $this->post('/admin/quality/recompute')->assertRedirect('/login');
    }

    public function test_member_gets_403_on_every_admin_url(): void
    {
        $member = $this->user();
        $lesson = Lesson::query()->firstOrFail();
        $tool = Tool::query()->where('slug', 'campaign-brief-builder')->firstOrFail();
        $report = ContentReport::query()->create(['lesson_id' => $lesson->id, 'reason' => 'other']);

        foreach ([...self::GET_URLS, '/admin/academy/lessons/'.$lesson->id, '/admin/tools/'.$tool->id] as $url) {
            $this->actingAs($member)->get($url)->assertForbidden();
        }
        $this->actingAs($member)->post('/admin/nudges', ['user_id' => $member->id, 'lesson_id' => $lesson->id])->assertForbidden();
        $this->actingAs($member)->patch('/admin/reports/'.$report->id, ['status' => 'resolved'])->assertForbidden();
        $this->actingAs($member)->post('/admin/quality/recompute')->assertForbidden();
        $this->actingAs($member)->post('/admin/preview/visitor')->assertForbidden();
        $this->actingAs($member)->post('/admin/academy/lessons/'.$lesson->id.'/variant', ['audience' => 'x', 'scenario' => 'y'])->assertForbidden();
        $this->assertSame('open', $report->fresh()->status);
        $this->assertDatabaseCount('learner_nudges', 0);
    }

    public function test_every_staff_role_reaches_the_control_room_and_finer_role_checks_still_apply(): void
    {
        foreach (Viewer::STAFF_ROLES as $role) {
            $this->actingAs($this->user($role))->get('/admin')->assertOk()->assertSee('غرفة التحكم');
        }
        // Past the baseline, each controller still decides by role.
        $this->actingAs($this->user('editor'))->get('/admin/academy')->assertOk();
        $this->actingAs($this->user('editor'))->get('/admin/users')->assertForbidden();
        $this->actingAs($this->user('finance'))->get('/admin/plans')->assertOk();
        $this->actingAs($this->user('finance'))->get('/admin/academy')->assertForbidden();
        $this->actingAs($this->user('operations'))->get('/admin/users')->assertOk();
        $this->actingAs($this->user('operations'))->get('/admin/tools')->assertForbidden();
        // An unverified staff account is still stopped by `verified` before the staff gate.
        $this->actingAs(User::factory()->unverified()->create(['platform_role' => 'admin']))->get('/admin')->assertRedirect(route('verification.notice'));
    }

    public function test_impersonation_can_still_be_stopped_from_the_members_session(): void
    {
        $admin = $this->user('admin');
        $member = $this->user();

        $this->actingAs($admin)->post('/admin/users/'.$member->id.'/impersonate')->assertRedirect('/dashboard');
        $this->assertSame($member->id, auth()->id());
        $this->get('/admin')->assertForbidden();
        $this->post('/admin/impersonation/stop')->assertRedirect(route('admin.users.index'));
        $this->assertSame($admin->id, auth()->id());
        $this->get('/admin')->assertOk();
    }

    public function test_admin_navigation_is_one_sidebar_partial_on_every_admin_screen(): void
    {
        $admin = $this->user('admin');
        $lesson = Lesson::query()->firstOrFail();
        $tool = Tool::query()->where('slug', 'campaign-brief-builder')->firstOrFail();

        foreach ([...self::GET_URLS, '/admin/academy/lessons/'.$lesson->id, '/admin/tools/'.$tool->id] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<body class="[^"]*\badmin-area\b/', $html, $url);
            $this->assertSame(1, substr_count($html, '<nav class="admin-nav"'), $url);
            $this->assertSame(1, substr_count($html, 'class="admin-nav-inner"'), $url);
            preg_match('/<nav class="admin-nav".*?<\/nav>/s', $html, $nav);
            $this->assertSame(1, substr_count($nav[0], 'aria-current="page"'), $url);
            $this->assertSame(12, substr_count($nav[0], '<a href='), $url);
        }
        // The member dashboard does not get the admin layout.
        $this->assertDoesNotMatchRegularExpression('/<body class="[^"]*admin-area/', $this->actingAs($admin)->get('/')->getContent());

        $css = file_get_contents(public_path('css/platform.css'));
        $this->assertStringContainsString('/* admin gaps 2026-11 */', $css);
        $this->assertStringContainsString('body.admin-area main{position:relative;padding-inline-start:232px}', $css);
        $this->assertMatchesRegularExpression('/@media \(max-width:980px\)\{\s*\.admin-nav\{flex-wrap:nowrap;overflow-x:auto/', $css);
    }
}
