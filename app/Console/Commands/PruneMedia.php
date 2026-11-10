<?php

namespace App\Console\Commands;

use App\Domain\Lab\Models\Media;
use App\Domain\Lab\Services\MediaLibrary;
use Illuminate\Console\Command;

/**
 * دورة حياة الملفات (plan 2026-11-09 البند 2 بند 6). A generated image is cheap to make and expensive to hoard, so a
 * file no saved asset points at is deleted once its grace period passes — bytes and row together, never one of them.
 *
 * Only orphans are touched: the moment a file is attached to a ToolOutput it is the member's, and the member's assets
 * are not deleted by a cron job. `--dry-run` prints what would go, because a cleanup you cannot rehearse is a risk.
 */
class PruneMedia extends Command
{
    protected $signature = 'platform:prune-media {--days=90} {--dry-run} {--limit=500}';

    protected $description = 'Delete stored files that no saved asset references (default: older than 90 days)';

    public function handle(MediaLibrary $library): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $orphans = Media::query()
            ->whereNull('tool_output_id')
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($orphans->isEmpty()) {
            $this->info("No unreferenced media older than {$days} days.");

            return self::SUCCESS;
        }

        $bytes = (int) $orphans->sum('bytes');
        if ($this->option('dry-run')) {
            foreach ($orphans as $media) {
                $this->line("would delete {$media->disk}:{$media->path} ({$media->humanSize()})");
            }
            $this->info('Would free '.number_format($bytes / 1048576, 1).' MB across '.$orphans->count().' files.');

            return self::SUCCESS;
        }

        foreach ($orphans as $media) {
            $library->delete($media);
        }
        $this->info('Pruned '.$orphans->count().' files, freeing '.number_format($bytes / 1048576, 1).' MB.');

        return self::SUCCESS;
    }
}
