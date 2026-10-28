<?php

namespace App\Console\Commands;

use App\Domain\Insights\Services\QualitySignalCalculator;
use Illuminate\Console\Command;

class ComputeQualitySignals extends Command
{
    protected $signature = 'platform:compute-quality-signals {--days=30}';

    protected $description = 'Compute learning-quality signals from learner behaviour (hourly)';

    public function handle(QualitySignalCalculator $calculator): int
    {
        $n = $calculator->compute(max(1, (int) $this->option('days')));
        $this->info("Open quality signals: {$n}");

        return self::SUCCESS;
    }
}
