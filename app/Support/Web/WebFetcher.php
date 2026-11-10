<?php

namespace App\Support\Web;

use DomainException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * قاعدة جلب الويب (spec 2026-11-09 §5، الموجة 0 البند 3) — غلاف واحد يخدم ثلاث ميزات.
 *
 * One wrapper for the capture gate, for reading a member's own site, and for watching a competitor page. Sharing the
 * code is the small reason; sharing the DISCIPLINE is the real one. Every fetch here passes, in order:
 *
 *   1. UrlGuard   — scheme, port, credentials, and the RESOLVED address, so a public hostname pointing at 10.0.0.5
 *                   cannot turn a form field into a window onto our own network.
 *   2. robots.txt — because a platform selling editorial trust cannot run a fetcher that ignores it.
 *   3. rate gate  — per host, so one slow site cannot spend the allowance the others need.
 *   4. cache      — the same page asked for twice in an hour leaves the server once.
 *
 * `text()` prefers Jina Reader, which returns clean Markdown instead of raw HTML — the difference between a model
 * reading content and a model reading a navigation menu. The direct fetch is the fallback, so no vendor is critical.
 *
 * Nothing here throws on an unreachable page: callers get a FetchedPage that answers false to ok(). It throws only
 * when the URL itself must be refused, which is a message for the member, not a failure to retry.
 */
class WebFetcher
{
    public function __construct(
        private readonly UrlGuard $guard,
        private readonly RobotsPolicy $robots,
    ) {}

    /** Readable text for a human or a model: Jina Reader first, direct HTML second. */
    public function text(string $url, ?int $cacheMinutes = null): FetchedPage
    {
        return $this->fetch($url, 'text', $cacheMinutes);
    }

    /** The body exactly as served — for sitemaps and any page where markup IS the content. */
    public function raw(string $url, ?int $cacheMinutes = null): FetchedPage
    {
        return $this->fetch($url, 'raw', $cacheMinutes);
    }

    /**
     * A feed an editor subscribed to deliberately. robots.txt does not apply: it governs CRAWLING — following links
     * a site did not hand you — while a feed exists to be polled and was entered by hand, not discovered. Applying
     * it here would silently kill curated subscriptions on sites whose robots blocks crawlers, which is most of them.
     *
     * The URL guard and the per-host rate window still apply: an editor pasting an internal address is exactly the
     * mistake the guard is for, and a feed that answers slowly should not starve the others.
     */
    public function feed(string $url): FetchedPage
    {
        return $this->fetch($url, 'raw', 0, respectRobots: false);
    }

    public function allowed(string $url): bool
    {
        return $this->guard->fetchable($url) && $this->robots->allows($url);
    }

    private function fetch(string $url, string $mode, ?int $cacheMinutes, bool $respectRobots = true): FetchedPage
    {
        $url = $this->guard->assertFetchable($url);
        $host = (string) parse_url($url, PHP_URL_HOST);

        $ttl = $cacheMinutes ?? (int) config('web.cache.page_minutes', 360);
        $key = 'web:page:'.$mode.':'.sha1($url);
        if ($ttl > 0 && is_array($hit = Cache::get($key))) {
            return new FetchedPage(
                url: $url, status: (int) $hit['status'], body: (string) $hit['body'],
                contentType: (string) $hit['content_type'], via: (string) $hit['via'], cached: true,
                title: $hit['title'] ?? null,
            );
        }

        if ($respectRobots && ! $this->robots->allows($url)) {
            throw new DomainException('موقع المصدر يمنع الجلب الآلي لهذا المسار (robots.txt). استعمل رابطاً آخر من الموقع نفسه.');
        }
        if (! $this->withinRate($host)) {
            throw new DomainException('بلغنا حدّ الطلبات لهذا الموقع الآن. أعد المحاولة بعد دقيقة.');
        }

        $page = $mode === 'text' ? $this->readable($url) : $this->direct($url, raw: true);
        $this->noteCall($host);

        if ($ttl > 0 && $page->ok()) {
            Cache::put($key, [
                'status' => $page->status, 'body' => $page->body, 'content_type' => $page->contentType,
                'via' => $page->via, 'title' => $page->title,
            ], now()->addMinutes($ttl));
        }

        return $page;
    }

    /** Jina Reader, then the direct page. A reader failure is logged and never shown to the member. */
    private function readable(string $url): FetchedPage
    {
        if ((bool) config('web.reader.enabled', true)) {
            try {
                $request = $this->request()->withHeaders(['Accept' => 'text/plain', 'X-Return-Format' => 'markdown']);
                if (filled($key = config('web.reader.key'))) {
                    $request = $request->withToken((string) $key);
                }
                $response = $request->get(rtrim((string) config('web.reader.base_url'), '/').'/'.$url);
                if ($response->successful() && trim($response->body()) !== '') {
                    $body = $this->cap($response->body());

                    return new FetchedPage(
                        url: $url, status: $response->status(), body: $body, contentType: 'text/markdown',
                        via: 'reader', title: $this->titleFromMarkdown($body),
                    );
                }
                Log::info('web reader returned nothing, falling back to a direct fetch', ['url' => $url, 'status' => $response->status()]);
            } catch (Throwable $exception) {
                Log::info('web reader failed, falling back to a direct fetch', ['url' => $url, 'reason' => $exception->getMessage()]);
            }
        }

        return $this->direct($url, raw: false);
    }

    private function direct(string $url, bool $raw): FetchedPage
    {
        try {
            $response = $this->request()->get($url);
        } catch (Throwable $exception) {
            return new FetchedPage(url: $url, status: 0, body: '', via: 'direct', title: null);
        }

        $body = $this->cap($response->body());
        $type = (string) $response->header('Content-Type');
        $title = $this->titleFromHtml($body);

        return new FetchedPage(
            url: $url,
            status: $response->status(),
            body: $raw ? $body : $this->stripHtml($body),
            contentType: $type,
            via: 'direct',
            title: $title,
        );
    }

    /**
     * Redirects are followed by hand, not by the client: a URL that passes the guard may redirect to one that would
     * not, and a client that follows automatically would make that request before anything could object.
     */
    private function request(): PendingRequest
    {
        return Http::withHeaders(['User-Agent' => (string) config('web.user_agent')])
            ->timeout((int) config('web.limits.timeout', 12))
            ->connectTimeout((int) config('web.limits.connect_timeout', 5))
            ->withoutRedirecting()
            ->withOptions(['http_errors' => false]);
    }

    private function cap(string $body): string
    {
        $max = (int) config('web.limits.max_bytes', 2097152);

        return strlen($body) > $max ? substr($body, 0, $max) : $body;
    }

    private function stripHtml(string $html): string
    {
        $html = preg_replace('#<(script|style|noscript|template)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<(br|/p|/div|/li|/h[1-6])\s*/?>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n\s*\n\s*\n+/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function titleFromHtml(string $html): ?string
    {
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return $title === '' ? null : Str::limit($title, 200, '');
        }

        return null;
    }

    private function titleFromMarkdown(string $markdown): ?string
    {
        if (preg_match('/^Title:\s*(.+)$/mu', $markdown, $m) || preg_match('/^#\s+(.+)$/mu', $markdown, $m)) {
            return Str::limit(trim($m[1]), 200, '');
        }

        return null;
    }

    private function withinRate(string $host): bool
    {
        foreach ($this->windows($host) as [$key, $limit]) {
            if ($limit > 0 && (int) Cache::get($key, 0) >= $limit) {
                return false;
            }
        }

        return true;
    }

    private function noteCall(string $host): void
    {
        foreach ($this->windows($host) as [$key, $limit, $ttl]) {
            if ($limit < 1) {
                continue;
            }
            Cache::add($key, 0, $ttl);
            Cache::increment($key);
        }
    }

    /** @return array<int, array{0:string,1:int,2:int}> */
    private function windows(string $host): array
    {
        $slug = sha1(strtolower($host));

        return [
            ['web:rate:m:'.$slug.':'.now()->format('YmdHi'), (int) config('web.rate.per_minute_per_host', 10), 120],
            ['web:rate:d:'.$slug.':'.now()->format('Ymd'), (int) config('web.rate.per_day_per_host', 300), 90000],
        ];
    }
}
