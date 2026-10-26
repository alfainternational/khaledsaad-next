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

Artisan::command('platform:doctor {--env=}', function () {
    $target = $this->option('env') ?: app()->environment();
    $checks = [
        'platform identity' => config('platform.id') === 'khaledsaad-next',
        'application key' => filled(config('app.key')),
        'storage writable' => is_writable(storage_path()),
        'database reachable' => rescue(fn () => DB::select('select 1') !== [], false),
    ];
    if ($target === 'production') {
        $checks['https url'] = str_starts_with((string) config('app.url'), 'https://');
        $checks['production database'] = config('database.default') !== 'sqlite';
        $checks['async queue'] = config('queue.default') !== 'sync';
        $checks['redis reachable'] = rescue(fn () => Redis::connection()->command('ping') !== null, false);
        $checks['worker heartbeat'] = (int) Cache::get('platform:worker_heartbeat', 0) >= now()->subMinutes(10)->timestamp;
        $checks['scheduler heartbeat'] = (int) Cache::get('platform:scheduler_heartbeat', 0) >= now()->subMinutes(10)->timestamp;
        $checks['transactional mail'] = ! in_array(config('mail.default'), ['log', 'array'], true);
        $checks['object storage'] = ! in_array(config('filesystems.default'), ['local', 'public'], true);
        $checks['openai configured'] = filled(config('services.openai.key'));
        $checks['payments configured'] = filled(config('services.payments.webhook_secret')) && filled(config('services.payments.plans.pro.price_id'));
        $checks['sitemap renderable'] = rescue(fn () => view('site.sitemap', [
            'articles' => collect(), 'courses' => collect(), 'tools' => collect(),
        ])->render() !== '', false);
        $backupMarker = (string) config('platform.backup_marker');
        $checks['recent backup'] = $backupMarker !== '' && is_file($backupMarker) && filemtime($backupMarker) >= now()->subDay()->timestamp;
    }
    foreach ($checks as $name => $passed) {
        $this->line(($passed ? '[PASS] ' : '[FAIL] ').$name);
    }

    return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
})->purpose('Validate platform launch requirements without printing secrets');

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
