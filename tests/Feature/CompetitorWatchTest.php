<?php

namespace Tests\Feature;

use App\Domain\Insights\Models\PageChange;
use App\Domain\Insights\Models\WatchedPage;
use App\Domain\Insights\Services\PageWatcher;
use App\Domain\Workspace\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\App\WatchController;
use App\Models\User;
use App\Support\Web\UrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * مراقبة المنافسين (spec 2026-11-09 §5، الموجة 2) — «هنا يولد الاشتراك المتكرّر».
 *
 * The tests that matter most here are the ones about SILENCE. Detecting change is easy; not reporting the hundred
 * daily differences that mean nothing is the whole product. A watcher that cries wolf is muted, and a muted watcher
 * is worse than none because the member believes they are being told.
 */
class CompetitorWatchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        UrlGuard::resolveUsing(fn (string $host) => $host === 'intranet.example.com' ? ['10.0.0.5'] : ['93.184.216.34']);
        $this->user = User::factory()->create(['audience' => 'owner']);
        $workspace = Workspace::query()->create(['name' => 'مساحة', 'slug' => 'watch-space']);
        $workspace->members()->attach($this->user, ['role' => 'owner']);
        $this->project = $workspace->projects()->create(['name' => 'متجر نَدى', 'slug' => 'watch-project', 'profile' => []]);
        config(['services.openai.key' => null, 'ai.chain' => ['cloudflare'], 'ai.providers.cloudflare.key' => null]);
    }

    /**
     * One fake for the whole test, serving the bodies in order. Re-calling Http::fake() ADDS stubs rather than
     * replacing them, so a second fake for the same URL never wins — which would make a "nothing changed" assertion
     * pass for the wrong reason: the page compared against itself.
     */
    private function fakePages(string ...$bodies): void
    {
        $sequence = Http::sequence();
        foreach ($bodies as $body) {
            $sequence->push($body, 200);
        }
        $sequence->whenEmpty(Http::response(end($bodies), 200));

        Http::fake([
            '*robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200),
            'r.jina.ai/*' => $sequence,
        ]);
    }

    private function fake(string $body): void
    {
        $this->fakePages($body);
    }

    private function watch(string $url = 'https://competitor.example/pricing'): WatchedPage
    {
        return WatchedPage::query()->create([
            'workspace_id' => $this->project->workspace_id, 'project_id' => $this->project->id, 'user_id' => $this->user->id,
            'label' => 'مراقبة سرّية لمنافس', 'url' => $url, 'url_hash' => WatchedPage::hashUrl($url),
        ]);
    }

    public function test_the_first_check_takes_a_baseline_and_reports_no_change(): void
    {
        $this->fake("# الأسعار\n\nالباقة الأساسية 199 ريال\nالباقة المتقدمة 399 ريال");
        $page = $this->watch();

        $result = app(PageWatcher::class)->check($page);

        $this->assertSame('first_run', $result['status']);
        $this->assertNull($result['change']);
        $this->assertNotNull($page->fresh()->checksum);
        $this->assertSame(0, PageChange::query()->count(), 'a first look is not news');
    }

    public function test_a_real_change_is_recorded_as_the_lines_that_moved(): void
    {
        $this->fakePages(
            "# الأسعار\n\nالباقة الأساسية 199 ريال\nالباقة المتقدمة 399 ريال",
            "# الأسعار\n\nالباقة الأساسية 149 ريال\nالباقة المتقدمة 399 ريال\nشحن مجاني فوق 200 ريال",
        );
        $page = $this->watch();
        app(PageWatcher::class)->check($page);
        $result = app(PageWatcher::class)->check($page->fresh());

        $this->assertSame('changed', $result['status']);
        $change = $result['change'];
        $this->assertContains('الباقة الأساسية 149 ريال', $change->added);
        $this->assertContains('شحن مجاني فوق 200 ريال', $change->added);
        $this->assertContains('الباقة الأساسية 199 ريال', $change->removed);
        $this->assertSame(1, $page->fresh()->change_count);
        $this->assertNotNull($page->fresh()->last_changed_at);

        // No model configured, so there is no sentence — and the diff above is untouched by that.
        $this->assertNull($change->summary);
    }

    public function test_the_noise_that_changes_every_day_is_not_a_change(): void
    {
        $watcher = app(PageWatcher::class);

        $monday = "# الأسعار\n\nآخر تحديث 2026/11/09 الساعة 10:15 ص\nالباقة الأساسية 199 ريال\n4,120 تقييم\nمنذ 3 ساعات\n/css/app.css?v=8f3ac21b9d4e5f60";
        $tuesday = "# الأسعار\n\nآخر تحديث 2026/11/10 الساعة 08:02 ص\nالباقة الأساسية 199 ريال\n4,133 تقييم\nمنذ 9 ساعات\n/css/app.css?v=11c0de77aa31bb42";

        $this->fakePages($monday, $tuesday);
        $page = $this->watch();
        $watcher->check($page);
        $result = $watcher->check($page->fresh());

        $this->assertSame('unchanged', $result['status'], 'dates, clocks, counters and asset hashes are not news');
        $this->assertSame(0, PageChange::query()->count());
        $this->assertSame(0, $page->fresh()->change_count);
    }

    public function test_normalise_strips_each_kind_of_noise_on_its_own(): void
    {
        $watcher = app(PageWatcher::class);

        foreach ([
            'آخر تحديث 2026/11/09' => 'آخر تحديث',
            'الساعة 10:15 ص' => 'الساعة',
            'منذ 3 ساعات' => '',
            '4,120 تقييم' => '',
            'رابط ?v=abc123def4567890' => 'رابط',
            'معرّف 8f3ac21b9d4e5f60aa11' => 'معرّف',
            'وسم ?utm_source=newsletter' => 'وسم',
        ] as $input => $expected) {
            $this->assertSame($expected, $watcher->normalise($input), $input);
        }

        // What actually matters survives untouched.
        $this->assertSame('الباقة الأساسية 199 ريال', $watcher->normalise('الباقة الأساسية 199 ريال'));
    }

    public function test_a_page_that_keeps_failing_is_paused_with_its_reason_kept(): void
    {
        Http::fake([
            '*robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200),
            'r.jina.ai/*' => Http::response('', 500),
            'https://competitor.example/*' => Http::response('', 503),
        ]);
        $page = $this->watch();
        $watcher = app(PageWatcher::class);

        for ($i = 0; $i < PageWatcher::MAX_FAILURES; $i++) {
            $result = $watcher->check($page->fresh());
            $this->assertSame('failed', $result['status']);
        }

        $page = $page->fresh();
        $this->assertFalse($page->is_active, 'a dead URL stops spending the host rate window and our attention');
        $this->assertTrue($page->isStalled());
        $this->assertStringContainsString('503', (string) $page->last_error);
    }

    public function test_an_internal_url_cannot_be_added_to_the_watchlist(): void
    {
        Http::fake();

        $this->actingAs($this->user)->post(route('app.watch.store'), [
            'project_id' => $this->project->id, 'label' => 'داخلي', 'url' => 'http://intranet.example.com/admin',
        ])->assertRedirect()->assertSessionHasErrors('watch');

        $this->assertSame(0, WatchedPage::query()->count());
        Http::assertNothingSent();
    }

    public function test_the_watchlist_is_capped_and_refuses_duplicates(): void
    {
        Http::fake();
        for ($i = 0; $i < WatchController::MAX_PAGES_PER_WORKSPACE; $i++) {
            $this->watch("https://competitor{$i}.example/pricing");
        }

        $this->actingAs($this->user)->post(route('app.watch.store'), [
            'project_id' => $this->project->id, 'label' => 'واحدة أخرى', 'url' => 'https://another.example/x',
        ])->assertSessionHasErrors('watch');

        $this->assertSame(WatchController::MAX_PAGES_PER_WORKSPACE, WatchedPage::query()->count());

        WatchedPage::query()->latest('id')->first()->delete();
        $this->actingAs($this->user)->post(route('app.watch.store'), [
            'project_id' => $this->project->id, 'label' => 'مكررة', 'url' => 'https://competitor0.example/pricing/',
        ])->assertSessionHasErrors('watch');
    }

    public function test_the_page_shows_what_changed_and_the_member_can_mark_it_read(): void
    {
        $this->fakePages("# الأسعار\n\nالباقة 199 ريال", "# الأسعار\n\nالباقة 149 ريال");
        $page = $this->watch();
        app(PageWatcher::class)->check($page);
        app(PageWatcher::class)->check($page->fresh());

        $response = $this->actingAs($this->user)->get(route('app.watch'))->assertOk()
            ->assertSee('مراقبة سرّية لمنافس')
            ->assertSee('الباقة 149 ريال')
            ->assertSee('الباقة 199 ريال')
            ->assertSee('جديد');
        $response->assertSee('1 تغيّراً لم تُراجعه بعد');

        $change = PageChange::query()->sole();
        $this->actingAs($this->user)->post(route('app.watch.seen', $change))->assertRedirect();
        $this->assertTrue($change->fresh()->seen);
    }

    public function test_a_stranger_cannot_read_or_delete_another_workspaces_watchlist(): void
    {
        Http::fake();
        $page = $this->watch();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->post(route('app.watch.check', $page))->assertForbidden();
        $this->actingAs($stranger)->delete(route('app.watch.destroy', $page))->assertForbidden();
        // A label that cannot collide with the page's own copy — the add form's placeholder is literally
        // «أسعار المنافس أ», and asserting on that would have read as a leak that was never there.
        $this->actingAs($stranger)->get(route('app.watch'))->assertOk()
            ->assertDontSee('مراقبة سرّية لمنافس')
            ->assertDontSee('competitor.example');
    }

    public function test_the_command_checks_the_longest_waiting_pages_and_is_scheduled(): void
    {
        $this->fake("# الأسعار\n\nالباقة 199 ريال");
        $this->watch('https://a.example/p');
        $this->watch('https://b.example/p');

        $this->artisan('platform:check-watched-pages --no-summary')->assertSuccessful();

        $this->assertSame(2, WatchedPage::query()->whereNotNull('checksum')->count());
        $this->assertTrue(collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->contains(fn ($e) => str_contains($e->command ?? '', 'platform:check-watched-pages')));
    }

    protected function tearDown(): void
    {
        UrlGuard::resolveUsing(null);
        Cache::flush();
        parent::tearDown();
    }
}
