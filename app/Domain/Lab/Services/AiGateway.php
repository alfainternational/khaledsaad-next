<?php

namespace App\Domain\Lab\Services;

use App\Domain\Lab\Ai\AiContext;
use App\Domain\Lab\Ai\ProviderChain;
use App\Domain\Lab\Ai\ProviderFailure;
use App\Domain\Lab\Ai\UsageRecorder;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The single door to any AI vendor (plan 2026-11-09). It no longer talks HTTP itself: it routes.
 *
 * Per run it walks the configured chain in order, skips a provider whose free allowance is spent, retries the next
 * one when a vendor fails, records what was consumed, and refuses the call outright when the workspace has used its
 * month. Call sites keep the same two-argument signature they always had; the optional context is what buys them
 * attribution, the quota and the sensitive-data routing.
 *
 * @see config/ai.php
 */
class AiGateway
{
    public function __construct(
        private readonly ProviderChain $chain,
        private readonly UsageRecorder $usage,
    ) {}

    /** Whether the platform can offer any AI feature at all — one configured provider is enough. */
    public function enabled(): bool
    {
        return $this->chain->configured();
    }

    /**
     * @return array{text: string, tokens: int, model: string, provider: string, cached: bool}
     */
    public function generate(string $input, ?string $instructions = null, ?AiContext $context = null): array
    {
        $context ??= AiContext::internal('unknown');

        if (! $this->chain->configured()) {
            throw new DomainException('مزود الذكاء الاصطناعي غير مفعّل. لم يتم خصم أي رصيد.');
        }

        $cacheTtl = $context->cacheMinutes ?? (int) config('ai.cache.ttl_minutes', 0);
        $cacheKey = $this->cacheKey($input, $instructions, $cacheTtl);
        if ($cacheKey && $context->cacheable && is_array($hit = Cache::get($cacheKey))) {
            $this->usage->record($context, $hit['provider'], $hit['model'], 0, 0, cached: true);

            return $hit + ['cached' => true];
        }

        $this->usage->assertWithinQuota($context->workspace);

        $candidates = $this->chain->candidates($context->sensitive);
        if ($candidates === []) {
            // No silent downgrade: a run carrying the member's own project data never falls back to a vendor
            // whose terms allow training on it, even when that vendor is the only one answering.
            if ($context->sensitive && $this->chain->candidates(false) !== []) {
                throw new DomainException('هذه الميزة تعالج بيانات مشروعك، ولا يوجد مزوّد لا يتدرّب على المدخلات مفعّل الآن. لم يُخصم أي رصيد.');
            }
            throw new DomainException('كل مزوّدي الذكاء الاصطناعي بلغوا حدّهم الآن. مدخلاتك محفوظة، أعد المحاولة بعد قليل.');
        }

        // A run may ask for less room than the platform allows, never for more: the ceiling stays in config.
        $maxOutputTokens = (int) config('ai.limits.max_output_tokens', 4000);
        if ($context->maxOutputTokens !== null) {
            $maxOutputTokens = max(1, min($maxOutputTokens, $context->maxOutputTokens));
        }
        foreach ($candidates as $provider) {
            try {
                $this->chain->noteCall($provider);
                $answer = $provider->text($input, $instructions, $maxOutputTokens);
            } catch (ProviderFailure $failure) {
                if ($failure->rateLimited) {
                    $this->chain->noteExhausted($provider);
                }
                Log::warning('ai provider failed, moving down the chain', [
                    'provider' => $failure->provider,
                    'rate_limited' => $failure->rateLimited,
                    'purpose' => $context->purpose,
                    'reason' => $failure->getMessage(),
                ]);

                continue;
            }

            $this->usage->record($context, $provider->name(), $answer['model'], $answer['input_tokens'], $answer['output_tokens']);

            $result = [
                'text' => $answer['text'],
                'tokens' => $answer['input_tokens'] + $answer['output_tokens'],
                'model' => $answer['model'],
                'provider' => $provider->name(),
            ];
            if ($cacheKey && $context->cacheable) {
                Cache::put($cacheKey, $result, now()->addMinutes($cacheTtl));
            }

            return $result + ['cached' => false];
        }

        throw new DomainException('تعذر إكمال تشغيل الذكاء الاصطناعي. احتفظ النظام بالمدخلات ويمكن إعادة المحاولة.');
    }

    /** Null disables caching: the chain signature is part of the key, so switching provider never serves a stale answer. */
    private function cacheKey(string $input, ?string $instructions, int $ttlMinutes): ?string
    {
        if ($ttlMinutes < 1) {
            return null;
        }

        return 'ai:text:'.sha1($this->chain->signature().'|'.($instructions ?? '').'|'.$input);
    }
}
