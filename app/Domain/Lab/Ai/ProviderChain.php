<?php

namespace App\Domain\Lab\Ai;

use Illuminate\Support\Facades\Cache;

/**
 * Builds the ordered list of providers AiGateway may try, and keeps the per-provider rate allowance.
 *
 * A spent free tier is skipped BEFORE the request rather than after a refusal: that is the whole point of
 * stacking free allowances, and it keeps one exhausted vendor from adding its timeout to every later run.
 */
class ProviderChain
{
    private const DRIVERS = [
        'openai' => OpenAiProvider::class,
        'gemini' => GeminiProvider::class,
        'cloudflare' => CloudflareProvider::class,
        'openai_compatible' => OpenAiCompatibleProvider::class,
    ];

    /** @var array<string, AiProvider|null> */
    private array $resolved = [];

    public function make(string $name): ?AiProvider
    {
        if (array_key_exists($name, $this->resolved)) {
            return $this->resolved[$name];
        }

        $config = $this->settings($name);
        $class = self::DRIVERS[(string) ($config['driver'] ?? '')] ?? null;

        return $this->resolved[$name] = $class ? new $class($name, $config) : null;
    }

    /** Provider entries resolved through any `*_config` indirection, so shared keys keep one source. */
    private function settings(string $name): array
    {
        $config = (array) config("ai.providers.{$name}", []);
        foreach ($config as $key => $value) {
            if (str_ends_with((string) $key, '_config') && is_string($value)) {
                $config[substr((string) $key, 0, -7)] = (string) config($value, '');
            }
        }

        return $config;
    }

    /**
     * Providers worth trying for this run, in order.
     *
     * @return list<AiProvider>
     */
    public function candidates(bool $sensitive = false): array
    {
        $names = (array) config('ai.chain', []);
        if ($sensitive && is_string($preferred = config('ai.sensitive_provider')) && $preferred !== '') {
            $names = array_values(array_unique(array_merge([$preferred], $names)));
        }

        $candidates = [];
        foreach ($names as $name) {
            $provider = $this->make((string) $name);
            if (! $provider || ! $provider->configured()) {
                continue;
            }
            if ($sensitive && $provider->trainsOnInput()) {
                continue;
            }
            if (! $this->withinRate($provider)) {
                continue;
            }
            $candidates[] = $provider;
        }

        return $candidates;
    }

    /** True when at least one provider in the chain holds its credentials — the platform's "AI is on" answer. */
    public function configured(): bool
    {
        foreach ((array) config('ai.chain', []) as $name) {
            $provider = $this->make((string) $name);
            if ($provider && $provider->configured()) {
                return true;
            }
        }

        return false;
    }

    /** Names and models of the whole chain: part of the cache key, so changing provider invalidates stored answers. */
    public function signature(): string
    {
        $parts = [];
        foreach ((array) config('ai.chain', []) as $name) {
            $provider = $this->make((string) $name);
            if ($provider && $provider->configured()) {
                $parts[] = $provider->name().':'.$provider->model();
            }
        }

        return implode('|', $parts);
    }

    public function withinRate(AiProvider $provider): bool
    {
        foreach ($this->windows($provider->name()) as [$key, $limit]) {
            if ($limit > 0 && (int) Cache::get($key, 0) >= $limit) {
                return false;
            }
        }

        return true;
    }

    public function noteCall(AiProvider $provider): void
    {
        foreach ($this->windows($provider->name()) as [$key, $limit, $ttl]) {
            if ($limit > 0) {
                Cache::add($key, 0, $ttl);
                Cache::increment($key);
            }
        }
    }

    /** A 429 means the allowance is gone before our own counter says so; trust the vendor and park the provider. */
    public function noteExhausted(AiProvider $provider): void
    {
        foreach ($this->windows($provider->name()) as [$key, $limit, $ttl]) {
            if ($limit > 0) {
                Cache::put($key, $limit, $ttl);
            }
        }
    }

    /** @return list<array{0: string, 1: int, 2: int}> key, limit, ttl seconds */
    private function windows(string $name): array
    {
        $rate = (array) config("ai.providers.{$name}.rate", []);

        return [
            ['ai:rate:'.$name.':m:'.now()->format('YmdHi'), (int) ($rate['per_minute'] ?? 0), 120],
            ['ai:rate:'.$name.':d:'.now()->format('Ymd'), (int) ($rate['per_day'] ?? 0), 90000],
        ];
    }
}
