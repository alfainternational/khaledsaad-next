<?php

namespace App\Domain\Lab\Ai;

/**
 * One AI vendor behind the gateway. A provider never charges credits, never caches and never records usage;
 * it only turns a prompt into text and reports what it consumed, so AiGateway stays the single place that
 * routes, caps and logs. Every failure leaves the gateway free to try the next provider in the chain.
 */
interface AiProvider
{
    /** Key of this provider in config('ai.providers'). */
    public function name(): string;

    /** True when every credential this provider needs is present. */
    public function configured(): bool;

    /**
     * True when the vendor's terms allow training on submitted input — common on free tiers.
     * Such providers are skipped for runs marked sensitive (see AiContext::$sensitive).
     */
    public function trainsOnInput(): bool;

    /** Model id this provider would use for a text run, for logging before the call. */
    public function model(): string;

    /**
     * @return array{text: string, input_tokens: int, output_tokens: int, model: string}
     *
     * @throws ProviderFailure
     */
    public function text(string $input, ?string $instructions, int $maxOutputTokens): array;
}
