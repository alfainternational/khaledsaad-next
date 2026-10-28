<?php

namespace App\Console\Commands;

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
            $checks['sitemap renderable'] = rescue(fn () => view('site.sitemap', [
                'articles' => collect(), 'courses' => collect(), 'tools' => collect(),
            ])->render() !== '', false);
            $backupMarker = (string) config('platform.backup_marker');
            $checks['recent backup'] = $backupMarker !== '' && is_file($backupMarker) && filemtime($backupMarker) >= now()->subDay()->timestamp;

            $warnings['object storage'] = ! in_array(config('filesystems.default'), ['local', 'public'], true);
            $warnings['openai configured'] = filled(config('services.openai.key'));
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
