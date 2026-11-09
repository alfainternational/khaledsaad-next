<?php

namespace App\Console\Commands;

use App\Domain\Lab\Models\Experiment;
use App\Domain\Lab\Services\ExperimentPicker;
use App\Domain\Lab\Services\StudioMetrics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * تجربة قيد التشغيل (blueprint §6.1 slot 5): «تُعتمد تلقائياً عند الدلالة الإحصائية».
 * For every running tool A/B: a two-proportion z-test on the save rate; with enough exposures per variant and p < 0.05
 * the winner is adopted through the same path as the studio's manual action, and the adoption is audited as automatic.
 */
class EvaluateExperiments extends Command
{
    protected $signature = 'platform:evaluate-experiments';

    protected $description = 'Adopt the winning variant of running A/B experiments once the save-rate difference is statistically significant (hourly)';

    public function handle(StudioMetrics $metrics, ExperimentPicker $picker): int
    {
        $adopted = 0;
        $running = Experiment::query()->where('status', 'running')->where('subject_type', 'tool')->where('metric', 'save_rate')->with('variants')->orderBy('id')->get();
        foreach ($running as $experiment) {
            $result = $metrics->significance($experiment);
            $samples = implode('/', $result['samples']);
            if (! $result['significant'] || $result['winner_variant_id'] === null) {
                $this->line("Experiment #{$experiment->id}: not significant yet (samples {$samples}, z=".($result['z'] ?? '—').', p='.($result['p'] ?? '—').').');

                continue;
            }
            $winner = $experiment->variants->firstWhere('id', $result['winner_variant_id']);
            $version = $picker->conclude($experiment, $winner);
            DB::table('audit_logs')->insert([
                'user_id' => null, 'action' => 'tool.experiment_ended', 'subject_type' => 'tool', 'subject_id' => (string) $experiment->subject_id,
                'context' => json_encode([
                    'experiment_id' => $experiment->id, 'winner_variant_id' => $winner->id, 'automatic' => true, 'adopted_version' => $version?->version,
                    'z' => $result['z'], 'p' => $result['p'], 'samples' => $result['samples'], 'min_sample' => $result['min_sample'], 'alpha' => StudioMetrics::ALPHA,
                ], JSON_UNESCAPED_UNICODE),
                'request_id' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $adopted++;
            $this->info("Experiment #{$experiment->id}: variant {$winner->label} adopted automatically (z={$result['z']}, p={$result['p']}, samples {$samples}).");
        }
        $this->info("Experiments evaluated: {$running->count()}, adopted: {$adopted}.");

        return self::SUCCESS;
    }
}
