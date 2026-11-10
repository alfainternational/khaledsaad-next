<?php

namespace App\Domain\Insights\Services;

use App\Domain\Insights\Models\PageChange;
use App\Domain\Insights\Models\WatchedPage;
use App\Domain\Lab\Ai\AiContext;
use App\Domain\Lab\Services\AiGateway;
use App\Domain\Lab\Services\TextDiff;
use App\Support\Web\WebFetcher;
use DomainException;
use Illuminate\Support\Str;
use Throwable;

/**
 * مراقب الصفحات (spec 2026-11-09 §5، الموجة 2).
 *
 * The hard part of change detection is not detecting change — it is NOT reporting the changes nobody cares about.
 * A page that prints today's date, a visitor counter, a rotating testimonial or a cache-busting asset hash differs
 * on every single fetch. A watcher that reports those is muted within a week and then deleted, so `normalise()`
 * below is the feature: everything it strips is a false alarm that would have cost the member their trust.
 *
 * The diff is computed in PHP and stands on its own. The model only writes a sentence ABOUT the diff, when one is
 * configured — so the feature degrades to "here is exactly what changed" rather than to nothing.
 */
class PageWatcher
{
    /** Below this, a difference is treated as noise rather than a change worth a notification. */
    public const MIN_CHANGED_LINES = 1;

    public const MAX_FAILURES = 5;

    public const MAX_COMPARED_LINES = 600;

    public function __construct(
        private readonly WebFetcher $web,
        private readonly AiGateway $ai,
    ) {}

    /**
     * @return array{status:string, change:?PageChange, error:?string}
     *                                                                status ∈ first_run | unchanged | changed | failed | skipped
     */
    public function check(WatchedPage $page, bool $summarise = true): array
    {
        if (! $page->is_active) {
            return ['status' => 'skipped', 'change' => null, 'error' => null];
        }

        try {
            $fetched = $this->web->text($page->url, cacheMinutes: 0);
            if (! $fetched->ok()) {
                return $this->fail($page, $fetched->status > 0 ? 'HTTP '.$fetched->status : 'تعذّر الوصول');
            }
        } catch (DomainException $exception) {
            // A refusal (guard, robots, rate) is a reason, not a transient failure: the member must read it.
            return $this->fail($page, Str::limit($exception->getMessage(), 180, ''));
        } catch (Throwable $exception) {
            report($exception);

            return $this->fail($page, 'خطأ غير متوقع أثناء الجلب');
        }

        $text = $this->normalise($fetched->text(20000));
        $checksum = hash('sha256', $text);

        if ($page->checksum === null) {
            $page->forceFill([
                'checksum' => $checksum, 'baseline_text' => $text, 'last_checked_at' => now(),
                'last_error' => null, 'failure_count' => 0,
            ])->save();

            return ['status' => 'first_run', 'change' => null, 'error' => null];
        }

        if ($page->checksum === $checksum) {
            $page->forceFill(['last_checked_at' => now(), 'last_error' => null, 'failure_count' => 0])->save();

            return ['status' => 'unchanged', 'change' => null, 'error' => null];
        }

        $diff = TextDiff::lines((string) $page->baseline_text, $text);
        $added = array_values(array_slice($diff['added'] ?? [], 0, 20));
        $removed = array_values(array_slice($diff['removed'] ?? [], 0, 20));

        if (count($added) + count($removed) < self::MIN_CHANGED_LINES) {
            // The hash moved but no line did: whitespace or ordering. Re-baseline silently; this is not news.
            $page->forceFill(['checksum' => $checksum, 'baseline_text' => $text, 'last_checked_at' => now()])->save();

            return ['status' => 'unchanged', 'change' => null, 'error' => null];
        }

        $change = PageChange::query()->create([
            'watched_page_id' => $page->id,
            'checksum' => $checksum,
            'added_lines' => count($added),
            'removed_lines' => count($removed),
            'added' => $added,
            'removed' => $removed,
            'summary' => $summarise ? $this->summarise($page, $added, $removed) : null,
        ]);

        $page->forceFill([
            'checksum' => $checksum, 'baseline_text' => $text, 'last_checked_at' => now(), 'last_changed_at' => now(),
            'change_count' => $page->change_count + 1, 'last_error' => null, 'failure_count' => 0,
        ])->save();

        return ['status' => 'changed', 'change' => $change, 'error' => null];
    }

    /**
     * Strip what differs without meaning. Each pattern here was a false alarm waiting to happen, and a false alarm
     * on a daily report is how a member learns to ignore the report.
     */
    public function normalise(string $text): string
    {
        $patterns = [
            // Dates in either calendar order, and Arabic month names.
            '/\b\d{1,4}[\/\-.]\d{1,2}[\/\-.]\d{1,4}\b/u' => ' ',
            '/\b\d{1,2}\s+(يناير|فبراير|مارس|أبريل|مايو|يونيو|يوليو|أغسطس|سبتمبر|أكتوبر|نوفمبر|ديسمبر)\s+\d{4}\b/u' => ' ',
            // Clock times and relative timestamps.
            '/\b\d{1,2}:\d{2}(:\d{2})?\s*(ص|م|AM|PM)?\b/iu' => ' ',
            '/\b(قبل|منذ)\s+\d+\s*(ثانية|دقيقة|دقائق|ساعة|ساعات|يوم|أيام|شهر|أشهر|سنة|سنوات)\b/u' => ' ',
            // Cache-busting query strings and asset hashes.
            '/\?(v|ver|t|cb|_)=[A-Za-z0-9._-]+/u' => ' ',
            '/\b[a-f0-9]{16,64}\b/i' => ' ',
            // Session-ish identifiers and tracking ids.
            // The separator goes with it, or a bare `?` is left behind and becomes the "change".
            '/[?&](utm_[a-z]+|gclid|fbclid|msclkid)=[^\s&]+/iu' => ' ',
            // "1,234 reviews" style counters move constantly and say nothing about the offer.
            '/\b\d[\d,،.]*\s*(تقييم|تقييمات|مشاهدة|مشاهدات|متابع|متابعون|زائر|زوار|review|reviews|views|followers)\b/iu' => ' ',
        ];
        $text = preg_replace(array_keys($patterns), array_values($patterns), $text) ?? $text;

        $lines = array_map(fn ($line) => trim(preg_replace('/\s+/u', ' ', (string) $line) ?? (string) $line), preg_split('/\R/u', $text) ?: []);

        $lines = array_values(array_filter($lines, fn ($l) => $l !== ''));

        // The diff is O(n × m), so a very long page is compared by its first lines rather than all of them. What a
        // competitor announces is not buried six hundred lines down.
        return implode("\n", array_slice($lines, 0, self::MAX_COMPARED_LINES));
    }

    /**
     * One sentence about a diff that already exists. It is never the only output: a failed or absent model leaves
     * the member with the exact added and removed lines, which is the part that cannot be wrong.
     */
    private function summarise(WatchedPage $page, array $added, array $removed): ?string
    {
        if (! $this->ai->enabled() || ($added === [] && $removed === [])) {
            return null;
        }

        $prompt = "الصفحة المراقَبة: {$page->label} ({$page->host()})\n\n";
        if ($added !== []) {
            $prompt .= "أُضيف:\n- ".implode("\n- ", array_slice($added, 0, 12))."\n\n";
        }
        if ($removed !== []) {
            $prompt .= "حُذف:\n- ".implode("\n- ", array_slice($removed, 0, 12))."\n";
        }

        try {
            $answer = $this->ai->generate(
                $prompt,
                'لخّص التغيّر في جملتين كحدّ أقصى لصاحب نشاط صغير يراقب منافساً: ما الذي تغيّر فعلاً، وهل يستدعي رداً. '
                .'لا تكرّر الأسطر، ولا تخترع ما ليس فيها، ولا تنصح بما لا يسنده التغيّر. إن كان التغيّر شكلياً فقل ذلك صراحة.',
                new AiContext(
                    workspace: $page->workspace,
                    userId: $page->user_id,
                    purpose: 'watch:'.$page->id,
                    operationKey: 'watch:'.$page->id.':'.substr($page->checksum ?? '', 0, 12),
                    // What a member watches reveals who they compete with; that is their business intelligence.
                    sensitive: true,
                    cacheable: false,
                    maxOutputTokens: 300,
                ),
            );

            return Str::limit(trim($answer['text']), 600, '…') ?: null;
        } catch (Throwable $exception) {
            // Quota, outage, refusal — the diff is already saved and is the part that matters.
            return null;
        }
    }

    private function fail(WatchedPage $page, string $error): array
    {
        $failures = (int) $page->failure_count + 1;
        $page->forceFill([
            'last_checked_at' => now(),
            'last_error' => $error,
            'failure_count' => $failures,
            // Paused, not retried forever: a dead URL should stop spending the host's rate window and our attention.
            'is_active' => $failures < self::MAX_FAILURES,
        ])->save();

        return ['status' => 'failed', 'change' => null, 'error' => $error];
    }
}
