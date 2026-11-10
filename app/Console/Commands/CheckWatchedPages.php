<?php

namespace App\Console\Commands;

use App\Domain\Insights\Models\WatchedPage;
use App\Domain\Insights\Services\PageWatcher;
use Illuminate\Console\Command;

/**
 * platform:check-watched-pages (spec 2026-11-09، الموجة 2). Scheduled daily; safe by hand; never fails the run.
 *
 * Checks the pages that have waited longest first, so a large watchlist drains evenly instead of starving its tail,
 * and stops at a per-run limit because the fetcher's per-host window would refuse the rest anyway.
 */
class CheckWatchedPages extends Command
{
    protected $signature = 'platform:check-watched-pages {--limit=60} {--page= : Check a single watched page by id} {--no-summary}';

    protected $description = 'Fetch the watched pages, record what changed, and summarise it when a model is available';

    public function handle(PageWatcher $watcher): int
    {
        $summarise = ! $this->option('no-summary');

        if ($this->option('page')) {
            $page = WatchedPage::query()->findOrFail((int) $this->option('page'));
            $this->report($page, $watcher->check($page, $summarise));

            return self::SUCCESS;
        }

        $pages = WatchedPage::query()
            ->where('is_active', true)
            ->orderByRaw('last_checked_at is null desc')
            ->orderBy('last_checked_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        $totals = ['changed' => 0, 'unchanged' => 0, 'first_run' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($pages as $page) {
            $result = $watcher->check($page, $summarise);
            $totals[$result['status']] = ($totals[$result['status']] ?? 0) + 1;
            $this->report($page, $result);
        }

        $this->info("Watched pages: {$totals['changed']} changed, {$totals['unchanged']} unchanged, {$totals['first_run']} baselined, {$totals['failed']} failed.");
        cache()->put('platform:watched_checked_at', now()->timestamp, now()->addDays(3));

        return self::SUCCESS;
    }

    private function report(WatchedPage $page, array $result): void
    {
        match ($result['status']) {
            'changed' => $this->line("  {$page->label}: تغيّر (+{$result['change']->added_lines} / -{$result['change']->removed_lines})"),
            'failed' => $this->warn("  {$page->label}: {$result['error']}".($page->isStalled() ? ' — أُوقفت بعد تكرار الفشل' : '')),
            'first_run' => $this->line("  {$page->label}: أُخذت النسخة المرجعية"),
            default => null,
        };
    }
}
