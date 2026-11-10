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
        /** Tighter output ceiling for this run. It can only lower the configured limit, never raise it. */
        public readonly ?int $maxOutputTokens = null,
        /** Cache lifetime for this run in minutes, overriding the platform default (the panel needs reproducibility). */
        public readonly ?int $cacheMinutes = null,
    ) {}

    /** Admin-side or internal run with no workspace to charge or cap. */
    public static function internal(string $purpose): self
    {
        return new self(purpose: $purpose);
    }

    /**
     * One call of a multi-call run (a panel voice, a round): same attribution and the same handling rules, with its own
     * idempotency key — so a retried panel records each voice once instead of collapsing ten calls into one row.
     */
    public function forStep(string $step): self
    {
        return new self(
            workspace: $this->workspace,
            userId: $this->userId,
            runId: $this->runId,
            purpose: $this->purpose,
            operationKey: $this->operationKey === null ? null : $this->operationKey.':'.$step,
            sensitive: $this->sensitive,
            cacheable: $this->cacheable,
            maxOutputTokens: $this->maxOutputTokens,
            cacheMinutes: $this->cacheMinutes,
        );
    }
}
