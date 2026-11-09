<?php

namespace App\Domain\Lab\Ai;

use App\Domain\Workspace\Models\Workspace;

/**
 * Who a run belongs to and how it may be handled. Passed to AiGateway::generate() so the gateway can apply the
 * workspace quota, attribute usage, and keep a client's own business data away from providers that train on input.
 */
class AiContext
{
    public function __construct(
        public readonly ?Workspace $workspace = null,
        public readonly ?int $userId = null,
        public readonly ?int $runId = null,
        /** Short label for the usage log, e.g. "tool:value-proposition" or "lesson:coach". */
        public readonly string $purpose = 'unknown',
        /** Stable key so a retried run records its usage once; a uuid is generated when absent. */
        public readonly ?string $operationKey = null,
        /** True for a client's own business data: providers whose free tier trains on input are then skipped. */
        public readonly bool $sensitive = false,
        /** False to force a fresh call even when an identical prompt was answered before. */
        public readonly bool $cacheable = true,
    ) {}

    /** Admin-side or internal run with no workspace to charge or cap. */
    public static function internal(string $purpose): self
    {
        return new self(purpose: $purpose);
    }
}
