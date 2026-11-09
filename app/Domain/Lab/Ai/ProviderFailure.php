<?php

namespace App\Domain\Lab\Ai;

use RuntimeException;

/**
 * A single provider failed. Never surfaced to the member: AiGateway catches it, moves to the next provider
 * in the chain, and only raises the Arabic DomainException once every candidate is exhausted.
 */
class ProviderFailure extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly bool $rateLimited = false,
    ) {
        parent::__construct($message);
    }
}
