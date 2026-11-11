<?php

namespace App\Support\Web;

use DomainException;
use RuntimeException;

/**
 * ما الذي يُسمح للخادم بجلبه (spec 2026-11-09، الموجة 0 البند 3).
 *
 * This exists because two features let a visitor choose a URL that the SERVER then requests. That shifts the request
 * from the visitor's network to ours, so anything our host can reach — an internal admin panel, a database admin UI,
 * a cloud metadata endpoint — becomes reachable by a stranger through a form field. The checks below are the whole
 * reason the fetcher is a shared service instead of an `Http::get()` in three controllers.
 *
 * The decisive one is that the resolved ADDRESS is judged, not the hostname: `intranet.example.com` is a perfectly
 * ordinary public name that may resolve to 10.0.0.5, and a hostname allow-list would let it straight through.
 */
class UrlGuard
{
    /** @var null|callable(string): array<int, string> */
    private static $resolver = null;

    /**
     * Swap the DNS lookup, for tests. Resolution is the heart of this guard, so a test must be able to say
     * "this hostname resolves to 10.0.0.5" without owning a domain that does — and without needing a network.
     */
    public static function resolveUsing(?callable $resolver): void
    {
        self::$resolver = $resolver;
    }

    /** @throws DomainException with a message safe to show a member */
    public function assertFetchable(string $url): string
    {
        $url = trim($url);
        if ($url === '' || mb_strlen($url) > 2000) {
            throw new DomainException('الرابط فارغ أو أطول مما يُقبل.');
        }

        $parts = parse_url($url);
        if ($parts === false) {
            throw new DomainException('الرابط غير صالح. اكتبه كاملاً بادئاً بـ https://');
        }
        // The scheme is judged before anything else: `file:///etc/passwd` parses with no host at all, and answering
        // "invalid URL" would hide the reason it was actually refused.
        if (! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            throw new DomainException('المقبول هو http أو https فقط.');
        }
        if (! isset($parts['host'])) {
            throw new DomainException('الرابط غير صالح. اكتبه كاملاً بادئاً بـ https://');
        }
        // Credentials in a URL are how an attacker smuggles auth to an internal service; they are never needed here.
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new DomainException('لا تُقبل الروابط التي تحمل اسم مستخدم أو كلمة مرور.');
        }
        $port = (int) ($parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80));
        if (! in_array($port, [80, 443], true)) {
            throw new DomainException('المقبول هو المنفذ القياسي للويب (80 أو 443).');
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        if ($host === '' || in_array($host, (array) config('web.blocked_hosts', []), true)) {
            throw new DomainException('هذا المضيف غير مسموح.');
        }
        // A bare name with no dot is an intranet name (`router`, `localhost`), never a public site.
        if (! str_contains($host, '.') && ! str_contains($host, ':')) {
            throw new DomainException('المضيف لا يبدو نطاقاً عاماً.');
        }

        foreach ($this->resolve($host) as $ip) {
            if (! $this->isPublicAddress($ip)) {
                throw new DomainException('هذا الرابط يشير إلى عنوان داخلي، ولا يجلب الخادم عناوين داخلية.');
            }
        }

        return $url;
    }

    /** True when the URL is fetchable; the reason is swallowed (for callers that only need a yes or no). */
    public function fetchable(string $url): bool
    {
        try {
            $this->assertFetchable($url);

            return true;
        } catch (DomainException) {
            return false;
        }
    }

    /** @return array<int, string> every address the host resolves to; an unresolvable host is a refusal, not a pass */
    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            return [trim($host, '[]')];
        }
        if (self::$resolver !== null) {
            $stubbed = array_values(array_filter((array) (self::$resolver)($host)));
            if ($stubbed === []) {
                throw new DomainException('تعذّر العثور على هذا النطاق.');
            }

            return $stubbed;
        }

        // A test that reaches real DNS is a bug, not a slow test. It depends on the network, takes seconds, and when
        // the lookup hiccups this throws «تعذّر العثور على هذا النطاق» — so the test fails once, passes the next two
        // times, and gets written off as noise. Refusing loudly turns that into a failure with a name on it.
        if (app()->runningUnitTests()) {
            throw new RuntimeException("UrlGuard tried to resolve «{$host}» over real DNS. Stub it in the test: UrlGuard::resolveUsing(fn () => ['93.184.216.34']);");
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
        $ips = [];
        foreach ($records as $record) {
            $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }
        $ips = array_values(array_filter($ips));

        if ($ips === []) {
            // gethostbyname returns the input unchanged on failure, which must not read as "resolved fine".
            $resolved = gethostbyname($host);
            if ($resolved !== $host && filter_var($resolved, FILTER_VALIDATE_IP)) {
                $ips[] = $resolved;
            }
        }
        if ($ips === []) {
            throw new DomainException('تعذّر العثور على هذا النطاق.');
        }

        return $ips;
    }

    private function isPublicAddress(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // ::1, fc00::/7 (unique local), fe80::/10 (link local), and IPv4-mapped addresses carrying a private v4.
            $packed = @inet_pton($ip);
            if ($packed === false) {
                return false;
            }
            if ($ip === '::1' || $ip === '::') {
                return false;
            }
            $first = ord($packed[0]);
            if (($first & 0xFE) === 0xFC) {
                return false;
            }
            if ($first === 0xFE && (ord($packed[1]) & 0xC0) === 0x80) {
                return false;
            }
            if (str_starts_with(strtolower($ip), '::ffff:')) {
                $mapped = substr($ip, 7);

                return filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $this->isPublicAddress($mapped) : false;
            }

            return true;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        foreach ((array) config('web.blocked_cidrs', []) as $cidr) {
            if ($this->inCidr($ip, (string) $cidr)) {
                return false;
            }
        }

        return true;
    }

    private function inCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, '32');
        $bits = (int) $bits;
        if ($bits < 1 || $bits > 32) {
            return false;
        }
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        $mask = -1 << (32 - $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
