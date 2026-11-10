<?php

/*
|--------------------------------------------------------------------------
| قاعدة جلب الويب (spec 2026-11-09 §5، الموجة 0 البند 3)
|--------------------------------------------------------------------------
|
| One wrapper serves three features: the source capture gate, reading a member's own site, and watching a competitor
| page. They share more than code — they share a risk. Two of the three fetch a URL a VISITOR typed, which is the
| textbook setup for SSRF: the server, not the visitor, makes the request, so it can be pointed at anything the
| server can reach that the visitor cannot.
|
| The defence is here and in UrlGuard, not in each caller:
|   - scheme and port allow-lists (no file://, no gopher://, no :22)
|   - the resolved IP is checked against private, loopback, link-local and reserved ranges — resolution, not the
|     hostname, because `internal.example.com` can resolve to 10.0.0.5 and a hostname check would wave it through
|   - every redirect hop is re-checked, because a public URL may redirect to a private one
|   - a byte cap, so a 2GB "page" cannot exhaust the process
|
| `robots.txt` is respected for the same reason a crawler should: the platform asks others to trust its editorial
| standards, and a fetcher that ignores robots is the cheapest possible way to lose that.
|
*/

return [

    'user_agent' => env('WEB_FETCH_USER_AGENT', 'AtharBot/1.0 (+https://next.khaledsaad.net/about)'),

    'limits' => [
        'timeout' => (int) env('WEB_FETCH_TIMEOUT', 12),
        'connect_timeout' => (int) env('WEB_FETCH_CONNECT_TIMEOUT', 5),
        'max_bytes' => (int) env('WEB_FETCH_MAX_BYTES', 2 * 1024 * 1024),
        'max_redirects' => (int) env('WEB_FETCH_MAX_REDIRECTS', 3),
        // Text handed to a model or stored as a profile; beyond this the page is truncated, never refused.
        'max_text_chars' => (int) env('WEB_FETCH_MAX_TEXT_CHARS', 40000),
    ],

    /*
    | Per host, not global: one slow competitor page must not spend the allowance the capture gate needs. The window
    | is a cache counter, the same mechanism the AI provider chain uses for its free tiers.
    */
    'rate' => [
        'per_minute_per_host' => (int) env('WEB_FETCH_RPM_PER_HOST', 10),
        'per_day_per_host' => (int) env('WEB_FETCH_RPD_PER_HOST', 300),
    ],

    'cache' => [
        'page_minutes' => (int) env('WEB_FETCH_CACHE_MINUTES', 360),
        'robots_minutes' => (int) env('WEB_FETCH_ROBOTS_CACHE_MINUTES', 1440),
    ],

    /*
    | Jina Reader turns a page into clean Markdown, which is what a model should read instead of raw HTML. It works
    | without a key at a lower rate; a key only raises the ceiling. Failure here is never fatal: the direct fetch
    | with tag stripping is the fallback, so one vendor cannot take the feature down.
    */
    'reader' => [
        'enabled' => (bool) env('WEB_FETCH_READER_ENABLED', true),
        'base_url' => env('JINA_READER_BASE_URL', 'https://r.jina.ai/'),
        'key' => env('JINA_API_KEY'),
    ],

    /*
    | Hosts the fetcher must never reach, whatever a URL claims. Cloud metadata first: it is the single most
    | valuable target an SSRF reaches, and it answers on a plain HTTP GET with no authentication at all.
    */
    'blocked_hosts' => ['metadata.google.internal', 'metadata.goog', 'instance-data'],

    'blocked_cidrs' => [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.0.2.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24',
        '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
    ],

];
