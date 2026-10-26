<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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
    }
    foreach ($checks as $name => $passed) {
        $this->line(($passed ? '[PASS] ' : '[FAIL] ').$name);
    }

    return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
})->purpose('Validate platform launch requirements without printing secrets');
