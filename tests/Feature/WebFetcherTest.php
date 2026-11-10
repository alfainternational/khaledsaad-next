<?php

namespace Tests\Feature;

use App\Support\Web\UrlGuard;
use App\Support\Web\WebFetcher;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * قاعدة جلب الويب (spec 2026-11-09، الموجة 0 البند 3). Two features let a visitor choose a URL that the SERVER then
 * requests, so most of these tests are about what the fetcher REFUSES. A fetcher that merely works is the dangerous
 * kind: the failure mode is not a broken page, it is a stranger reading our own network through a form field.
 */
class WebFetcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Hermetic DNS: the guard judges the resolved address, so the test owns what each name resolves to.
        UrlGuard::resolveUsing(fn (string $host) => match ($host) {
            'example.com', 'other.example', 'example.org' => ['93.184.216.34'],
            'intranet.example.com' => ['10.0.0.5'],
            'split.example.com' => ['93.184.216.34', '192.168.1.9'],
            'nowhere.example' => [],
            default => ['93.184.216.34'],
        });
    }

    private function fetcher(): WebFetcher
    {
        return app(WebFetcher::class);
    }

    private function guard(): UrlGuard
    {
        return app(UrlGuard::class);
    }

    private static function robotsAllowAll(): array
    {
        return ['*robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200)];
    }

    public function test_it_refuses_everything_that_is_not_a_public_web_url(): void
    {
        Http::fake();
        $refusals = [
            'file:///etc/passwd' => 'http أو https',
            'gopher://example.com/' => 'http أو https',
            'https://user:secret@example.com/' => 'اسم مستخدم أو كلمة مرور',
            'https://example.com:22/' => 'المنفذ القياسي',
            'http://localhost/admin' => 'لا يبدو نطاقاً عاماً',
            'http://127.0.0.1/' => 'عنوان داخلي',
            'http://10.0.0.5/' => 'عنوان داخلي',
            'http://192.168.1.1/' => 'عنوان داخلي',
            'http://172.16.0.9/' => 'عنوان داخلي',
            // The single most valuable SSRF target: cloud metadata answers an unauthenticated GET.
            'http://169.254.169.254/latest/meta-data/' => 'عنوان داخلي',
            'http://[::1]/' => 'عنوان داخلي',
            'http://metadata.google.internal/' => 'غير مسموح',
            '' => 'فارغ',
        ];

        foreach ($refusals as $url => $expected) {
            try {
                $this->guard()->assertFetchable((string) $url);
                $this->fail("expected a refusal for: {$url}");
            } catch (DomainException $exception) {
                $this->assertStringContainsString($expected, $exception->getMessage(), $url);
            }
        }

        Http::assertNothingSent();
    }

    public function test_a_public_hostname_that_resolves_to_a_private_address_is_refused(): void
    {
        // The hostname is ordinary; only the resolved address gives it away. A hostname allow-list passes this.
        $this->assertFalse($this->guard()->fetchable('http://intranet.example.com/'), 'resolves to 10.0.0.5');

        // One public answer does not redeem a private one: a split-horizon name is refused on the worst address.
        $this->assertFalse($this->guard()->fetchable('http://split.example.com/'), 'one of its addresses is private');

        // A name that resolves to nothing is a refusal, not a pass — gethostbyname returns the input on failure.
        $this->assertFalse($this->guard()->fetchable('http://nowhere.example/'));
    }

    public function test_it_reads_a_page_through_the_reader_and_caches_it(): void
    {
        Http::fake(self::robotsAllowAll() + [
            'r.jina.ai/*' => Http::response("Title: متجر نَدى\n\n# متجر نَدى\n\nسيروم طبيعي بلا عطور.", 200),
        ]);

        $page = $this->fetcher()->text('https://example.com/about');

        $this->assertTrue($page->ok());
        $this->assertSame('reader', $page->via);
        $this->assertSame('متجر نَدى', $page->title);
        $this->assertStringContainsString('سيروم طبيعي', $page->text());
        $this->assertFalse($page->cached);

        $again = $this->fetcher()->text('https://example.com/about');
        $this->assertTrue($again->cached, 'the same page within the window must not leave the server twice');
        Http::assertSentCount(2); // robots + one reader call
    }

    public function test_a_reader_failure_falls_back_to_the_page_itself(): void
    {
        Http::fake(self::robotsAllowAll() + [
            'r.jina.ai/*' => Http::response('', 500),
            'https://example.com/*' => Http::response('<html><head><title>عن المتجر</title></head><body><script>bad()</script><p>نص الصفحة</p></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $page = $this->fetcher()->text('https://example.com/about');

        $this->assertTrue($page->ok());
        $this->assertSame('direct', $page->via);
        $this->assertSame('عن المتجر', $page->title);
        $this->assertStringContainsString('نص الصفحة', $page->body);
        $this->assertStringNotContainsString('bad()', $page->body, 'scripts are not content');
        $this->assertStringNotContainsString('<p>', $page->body);
    }

    public function test_robots_disallow_is_respected_and_the_page_is_never_requested(): void
    {
        Http::fake([
            '*robots.txt' => Http::response("User-agent: *\nDisallow: /private/\n", 200),
            '*' => Http::response('secret', 200),
        ]);

        try {
            $this->fetcher()->text('https://example.com/private/report');
            $this->fail('expected a robots refusal');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('robots.txt', $exception->getMessage());
        }

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/private/report'));
        $this->assertTrue($this->fetcher()->text('https://example.com/public/ok')->ok(), 'other paths stay allowed');
    }

    public function test_a_rule_for_our_own_agent_wins_over_the_wildcard_group(): void
    {
        Http::fake([
            '*robots.txt' => Http::response("User-agent: *\nDisallow: /\n\nUser-agent: AtharBot\nAllow: /\n", 200),
            '*' => Http::response('# صفحة', 200),
        ]);

        $this->assertTrue($this->fetcher()->allowed('https://example.com/anything'));
    }

    public function test_a_host_that_refuses_to_serve_robots_is_left_alone(): void
    {
        Http::fake(['*robots.txt' => Http::response('no', 403), '*' => Http::response('body', 200)]);

        $this->assertFalse($this->fetcher()->allowed('https://example.com/page'), '403 on robots is a site declining automation');
    }

    public function test_the_per_host_rate_window_stops_a_run_away_loop(): void
    {
        config(['web.rate.per_minute_per_host' => 2, 'web.cache.page_minutes' => 0]);
        Http::fake(self::robotsAllowAll() + ['r.jina.ai/*' => Http::response('# صفحة', 200)]);

        $this->fetcher()->text('https://example.com/a');
        $this->fetcher()->text('https://example.com/b');

        try {
            $this->fetcher()->text('https://example.com/c');
            $this->fail('expected the host rate window to refuse the third call');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('حدّ الطلبات', $exception->getMessage());
        }

        // A different host has its own window, so one slow site cannot starve the others.
        $this->assertTrue($this->fetcher()->text('https://other.example/a')->ok());
    }

    public function test_an_unreachable_page_is_a_result_not_an_exception(): void
    {
        Http::fake(self::robotsAllowAll() + ['*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);

        $page = $this->fetcher()->text('https://example.com/down');

        $this->assertFalse($page->ok());
        $this->assertSame(0, $page->status);
        $this->assertSame('', $page->body);
    }

    public function test_an_oversized_body_is_truncated_rather_than_loaded_whole(): void
    {
        config(['web.limits.max_bytes' => 1024]);
        Http::fake(self::robotsAllowAll() + ['r.jina.ai/*' => Http::response(str_repeat('ا', 5000), 200)]);

        $page = $this->fetcher()->text('https://example.com/huge');

        $this->assertSame(1024, $page->bytes());
    }

    public function test_raw_keeps_the_markup_that_a_feed_needs(): void
    {
        Http::fake(self::robotsAllowAll() + ['https://example.com/feed*' => Http::response('<rss><channel><item><title>خبر</title></item></channel></rss>', 200, ['Content-Type' => 'application/rss+xml'])]);

        $page = $this->fetcher()->raw('https://example.com/feed.xml');

        $this->assertStringContainsString('<item>', $page->body, 'markup is the content for a feed');
        $this->assertSame('direct', $page->via);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'r.jina.ai'));
    }

    protected function tearDown(): void
    {
        UrlGuard::resolveUsing(null);
        Cache::flush();
        parent::tearDown();
    }
}
