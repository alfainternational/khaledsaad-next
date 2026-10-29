<?php

namespace App\Console\Commands;

use App\Domain\Content\Models\SourceFeed;
use App\Domain\Content\Services\SourceFetcher;
use Illuminate\Console\Command;

/** platform:fetch-sources — the capture gate (blueprint §6.3). Scheduled daily; safe to run by hand; never fails on network errors. */
class FetchSources extends Command
{
    protected $signature = 'platform:fetch-sources {--feed= : Fetch a single feed by id}';

    protected $description = 'Fetch the watched RSS/Atom sources into the capture gate with relevance scores';

    public function handle(SourceFetcher $fetcher): int
    {
        if ($this->option('feed')) {
            $feed = SourceFeed::query()->findOrFail((int) $this->option('feed'));
            $result = $fetcher->fetch($feed);
            $this->report($feed, $result);
            $this->info("Fetched {$result['fetched']}, created {$result['created']}, skipped {$result['skipped']}.");

            return self::SUCCESS;
        }
        $totals = $fetcher->fetchAll(fn (SourceFeed $feed, array $result) => $this->report($feed, $result));
        $this->info("Sources: {$totals['fetched']} fetched, {$totals['created']} new, {$totals['skipped']} already known, {$totals['failed']} feed(s) unreachable.");
        cache()->put('platform:sources_fetched_at', now()->timestamp, now()->addDays(3));

        return self::SUCCESS;
    }

    private function report(SourceFeed $feed, array $result): void
    {
        if (! empty($result['error'])) {
            $this->warn("  {$feed->name}: unreachable ({$result['error']})");
        } else {
            $this->line("  {$feed->name}: {$result['created']} new / {$result['fetched']} fetched");
        }
    }
}
