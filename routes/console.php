<?php

use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Workspace\Models\Workspace;
use App\Jobs\RecordWorkerHeartbeat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('platform:recover-runs {--minutes=15}', function (CreditLedger $ledger) {
    $cutoff = now()->subMinutes(max(1, (int) $this->option('minutes')));
    $runIds = DB::table('tool_runs')
        ->where('status', 'running')
        ->where('reserved_credits', '>', 0)
        ->where('updated_at', '<=', $cutoff)
        ->orderBy('id')
        ->pluck('id');

    $recovered = 0;
    foreach ($runIds as $runId) {
        DB::transaction(function () use ($runId, $ledger, &$recovered): void {
            $run = DB::table('tool_runs')->where('id', $runId)->lockForUpdate()->first();
            if (! $run || $run->status !== 'running' || (int) $run->reserved_credits < 1) {
                return;
            }

            $workspace = Workspace::query()->findOrFail($run->workspace_id);
            $ledger->record(
                $workspace,
                "run:{$run->public_id}:release",
                'release',
                (int) $run->reserved_credits,
                'استرجاع حجز تشغيل متوقف',
                ['run_id' => $run->id]
            );
            DB::table('tool_runs')->where('id', $run->id)->update([
                'status' => 'failed', 'reserved_credits' => 0, 'error_code' => 'stale_recovered', 'updated_at' => now(),
            ]);
            $recovered++;
        });
    }

    $this->info("Recovered {$recovered} stale runs.");

    return self::SUCCESS;
})->purpose('Release credits held by interrupted tool runs');

Schedule::call(fn () => Cache::put('platform:scheduler_heartbeat', now()->timestamp, now()->addMinutes(15)))
    ->name('platform-scheduler-heartbeat')->everyMinute();
Schedule::job(new RecordWorkerHeartbeat)->name('platform-worker-heartbeat')->everyMinute();
Schedule::command('platform:recover-runs')->everyFiveMinutes()->withoutOverlapping();
// Shared hosting has no Supervisor: drain the queue from the scheduler every minute.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->name('platform-queue-drain')->everyMinute()->withoutOverlapping();
Schedule::command('platform:backup')->dailyAt('03:10')->withoutOverlapping();
Schedule::command('platform:compute-quality-signals')->hourly()->withoutOverlapping();
Schedule::command('platform:send-checkin-reminders')->dailyAt('08:00')->withoutOverlapping();
