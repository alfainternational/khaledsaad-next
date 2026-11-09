<?php

namespace App\Domain\Lab\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Shared plumbing for the HTTP providers: config access, the request builder with the platform timeout and
 * retry policy, and the 429 detection that tells the chain a free tier is spent for now.
 */
abstract class HttpProvider implements AiProvider
{
    /** Statuses that mean "this provider is done for now": no retry, and the chain parks it. */
    private const FINAL_STATUSES = [429, 402, 403];

    public function __construct(protected readonly string $providerName, protected readonly array $config) {}

    public function name(): string
    {
        return $this->providerName;
    }

    public function trainsOnInput(): bool
    {
        return (bool) ($this->config['trains_on_input'] ?? false);
    }

    public function model(): string
    {
        return (string) ($this->config['model'] ?? '');
    }

    protected function setting(string $key, string $default = ''): string
    {
        return trim((string) ($this->config[$key] ?? $default));
    }

    /**
     * Retries are for a wobbling vendor, never for a spent allowance: a 429 is a final answer from this provider,
     * so retrying it would only add its sleep to every run before the chain gets to the next free tier.
     */
    protected function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('ai.limits.timeout', 60))
            ->retry(
                (int) config('ai.limits.retries', 2),
                300,
                function (Throwable $exception) {
                    $status = $exception instanceof RequestException ? (int) $exception->response->status() : 0;

                    return ! in_array($status, self::FINAL_STATUSES, true);
                },
                throw: false,
            );
    }

    /** A spent free tier must not look like an outage: the chain skips the provider instead of burning retries. */
    protected function fail(string $message, int $status = 0): never
    {
        throw new ProviderFailure(
            $this->providerName.': '.$message,
            $this->providerName,
            rateLimited: in_array($status, self::FINAL_STATUSES, true),
        );
    }
}
