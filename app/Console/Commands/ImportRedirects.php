<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Upsert legacy-URL redirects from a CSV (from_path,to_path,status_code).
 * Paths are stored URL-decoded so Arabic slugs match what the middleware looks up.
 */
class ImportRedirects extends Command
{
    protected $signature = 'platform:import-redirects {file : CSV with from_path,to_path,status_code} {--dry-run}';

    protected $description = 'Import or update 301/302/307/308/410 redirects from a CSV file';

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        if (! $header || array_slice(array_map('trim', $header), 0, 2) !== ['from_path', 'to_path']) {
            $this->error('CSV header must start with: from_path,to_path[,status_code]');

            return self::FAILURE;
        }

        $imported = 0;
        $skipped = 0;
        $line = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if (count($row) < 2 || trim($row[0]) === '') {
                continue;
            }
            $from = self::normalize($row[0]);
            $to = self::normalize($row[1]);
            $status = (int) ($row[2] ?? 301) ?: 301;

            $problem = self::validate($from, $to, $status);
            if ($problem) {
                $this->warn("line {$line}: {$problem} ({$row[0]} -> {$row[1]})");
                $skipped++;

                continue;
            }
            if ($this->option('dry-run')) {
                $this->line("{$status} {$from} -> {$to}");
                $imported++;

                continue;
            }
            DB::table('redirects')->updateOrInsert(
                ['from_path' => $from],
                ['to_path' => $to, 'status_code' => $status, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
            );
            $imported++;
        }
        fclose($handle);

        $this->info(($this->option('dry-run') ? 'Would import ' : 'Imported ')."{$imported} redirect(s), skipped {$skipped}.");

        return self::SUCCESS;
    }

    public static function normalize(string $path): string
    {
        $path = trim($path);
        $path = preg_replace('#^https?://[^/]+#i', '', $path) ?? $path;
        $path = rawurldecode($path);
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        // Keep a leading "//" intact so validate() can reject protocol-relative targets.
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public static function validate(string $from, string $to, int $status): ?string
    {
        if (! in_array($status, [301, 302, 307, 308, 410], true)) {
            return 'unsupported status code';
        }
        foreach ([$from, $to] as $p) {
            if (! str_starts_with($p, '/') || str_starts_with($p, '//') || preg_match('/[\\\\\s\x00-\x1f]/', $p) || parse_url($p, PHP_URL_HOST) !== null) {
                return 'path must be site-relative without whitespace or backslashes';
            }
        }
        if ($from === '/') {
            return 'the home page cannot be redirected';
        }
        if ($status !== 410 && $from === $to) {
            return 'redirect loops to itself';
        }

        return null;
    }
}
