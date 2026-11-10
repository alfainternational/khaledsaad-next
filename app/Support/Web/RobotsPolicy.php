<?php

namespace App\Support\Web;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * احترام robots.txt (spec 2026-11-09، الموجة 0 البند 3).
 *
 * A platform that asks publishers to trust its editorial standards cannot run a fetcher that ignores their stated
 * wishes; this is reputational, not legal. The rules are cached per host for a day — re-reading robots.txt on every
 * page would double the requests we make to the very sites we are trying not to burden.
 *
 * Unreachable robots.txt means ALLOWED, which is the convention: a site that cannot answer has not said no. A 403 or
 * 401 on robots.txt is treated as a refusal instead, because that is a site actively declining automated access.
 */
class RobotsPolicy
{
    public function allows(string $url, ?string $userAgent = null): bool
    {
        $parts = parse_url($url);
        if (! isset($parts['scheme'], $parts['host'])) {
            return false;
        }
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        $rules = $this->rulesFor($parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : ''));

        if ($rules === null) {
            return false; // the host refused to serve robots.txt at all
        }

        $agent = strtolower($userAgent ?? (string) config('web.user_agent'));
        $group = $this->groupFor($rules, $agent);
        if ($group === []) {
            return true;
        }

        // Longest matching rule wins, and Allow beats Disallow at equal length — the de-facto standard behaviour.
        $decision = true;
        $matched = -1;
        foreach ($group as [$type, $pattern]) {
            if ($pattern === '') {
                continue;
            }
            if ($this->matches($path, $pattern) && strlen($pattern) > $matched) {
                $matched = strlen($pattern);
                $decision = $type === 'allow';
            }
        }

        return $decision;
    }

    /** @return array<int, array{0:string,1:string,2:string}>|null [agent, type, pattern]; null when the host refused */
    private function rulesFor(string $origin): ?array
    {
        $key = 'web:robots:'.sha1($origin);
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }
        if ($cached === 'refused') {
            return null;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => (string) config('web.user_agent')])
                ->timeout((int) config('web.limits.connect_timeout', 5))
                ->get(rtrim($origin, '/').'/robots.txt');
            $rules = in_array($response->status(), [401, 403], true)
                ? 'refused'
                : ($response->successful() ? $this->parse($response->body()) : []);
        } catch (Throwable) {
            $rules = []; // unreachable is not a refusal
        }

        Cache::put($key, $rules, now()->addMinutes((int) config('web.cache.robots_minutes', 1440)));

        return is_array($rules) ? $rules : null;
    }

    /** @return array<int, array{0:string,1:string,2:string}> */
    private function parse(string $body): array
    {
        $rules = [];
        $agents = [];
        $expectingAgent = true;
        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                if (! $expectingAgent) {
                    $agents = [];
                    $expectingAgent = true;
                }
                $agents[] = strtolower($value);

                continue;
            }
            if (in_array($field, ['allow', 'disallow'], true) && $agents !== []) {
                $expectingAgent = false;
                foreach ($agents as $agent) {
                    $rules[] = [$agent, $field === 'allow' ? 'allow' : 'disallow', $value];
                }
            }
        }

        return $rules;
    }

    /** The most specific matching group: our own name beats `*`, as the standard requires. */
    private function groupFor(array $rules, string $agent): array
    {
        $exact = [];
        $wildcard = [];
        foreach ($rules as [$ruleAgent, $type, $pattern]) {
            if ($ruleAgent !== '*' && str_contains($agent, $ruleAgent)) {
                $exact[] = [$type, $pattern];
            } elseif ($ruleAgent === '*') {
                $wildcard[] = [$type, $pattern];
            }
        }

        return $exact !== [] ? $exact : $wildcard;
    }

    private function matches(string $path, string $pattern): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $pattern = rtrim($pattern, '$');
        $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).($anchored ? '$' : '').'#';

        return (bool) preg_match($regex, $path);
    }
}
