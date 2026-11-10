<?php

namespace App\Console\Commands;

use App\Http\Controllers\PublicSiteController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Launch-readiness check. Hard requirements print PASS/FAIL and decide the exit code;
 * optional integrations (storage, AI, payments) print PASS/WARN so a staging launch on
 * shared hosting can proceed before every provider key exists.
 */
class PlatformDoctor extends Command
{
    protected $signature = 'platform:doctor {--env=}';

    protected $description = 'Validate platform launch requirements without printing secrets';

    public function handle(): int
    {
        $target = $this->option('env') ?: app()->environment();

        $checks = [
            'platform identity' => config('platform.id') === 'khaledsaad-next',
            'application key' => filled(config('app.key')),
            'storage writable' => is_writable(storage_path()),
            'database reachable' => rescue(fn () => DB::select('select 1') !== [], false),
        ];
        $warnings = [];

        if ($target === 'production') {
            $checks['https url'] = str_starts_with((string) config('app.url'), 'https://');
            $checks['debug disabled'] = config('app.debug') === false;
            $checks['production database'] = config('database.default') !== 'sqlite';
            $checks['async queue'] = config('queue.default') !== 'sync';
            $checks['shared cache store'] = in_array(config('cache.default'), ['redis', 'database', 'memcached', 'dynamodb'], true);
            $usesRedis = in_array('redis', [config('cache.default'), config('queue.default'), config('session.driver')], true);
            if ($usesRedis) {
                $checks['redis reachable'] = rescue(fn () => Redis::connection()->command('ping') !== null, false);
            }
            $checks['worker heartbeat'] = (int) Cache::get('platform:worker_heartbeat', 0) >= now()->subMinutes(10)->timestamp;
            $checks['scheduler heartbeat'] = (int) Cache::get('platform:scheduler_heartbeat', 0) >= now()->subMinutes(10)->timestamp;
            $checks['transactional mail'] = ! in_array(config('mail.default'), ['log', 'array'], true);
            // Through the real controller action, so the check cannot drift from the data the sitemap view expects.
            $checks['sitemap renderable'] = rescue(fn () => str_contains((string) app(PublicSiteController::class)->sitemap()->getContent(), '<urlset'), false);
            $backupMarker = (string) config('platform.backup_marker');
            $checks['recent backup'] = $backupMarker !== '' && is_file($backupMarker) && filemtime($backupMarker) >= now()->subDay()->timestamp;

            // Measures the `assets` disk, not the default one. The default is local BY DESIGN (plan 2026-11-09 البند 2):
            // sessions, cache and logs stay on the server so a bad bucket credential degrades one feature, not the
            // platform. Asking about `filesystems.default` therefore warned forever on a correctly configured host.
            $assets = (array) config('filesystems.disks.assets', []);
            $warnings['object storage'] = ($assets['driver'] ?? 'local') === 's3'
                && filled($assets['bucket'] ?? null)
                && filled($assets['key'] ?? null)
                && filled($assets['secret'] ?? null);
            $warnings['ai provider configured'] = app(\App\Domain\Lab\Services\AiGateway::class)->enabled();
            $warnings['paypal configured'] = filled(config('services.paypal.client_id')) && filled(config('services.paypal.secret')) && filled(config('services.paypal.webhook_id'));
            $warnings['paypal live mode'] = config('services.paypal.mode') === 'live';
        }

        foreach ($checks as $name => $passed) {
            $this->line(($passed ? '[PASS] ' : '[FAIL] ').$name);
        }
        foreach ($warnings as $name => $passed) {
            $this->line(($passed ? '[PASS] ' : '[WARN] ').$name);
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
