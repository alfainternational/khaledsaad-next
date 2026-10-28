<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Daily logical backup of the primary database (mysqldump / pg_dump) into storage/app/backups,
 * keeping the last N files and touching the backup marker that platform:doctor checks.
 * Credentials are passed through the process environment, never on the command line.
 */
class PlatformBackup extends Command
{
    protected $signature = 'platform:backup {--keep=7}';

    protected $description = 'Dump the primary database to storage/app/backups and refresh the backup marker';

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir, 0750);
        $file = $dir.'/db-'.now()->format('Y-m-d-His').'.sql.gz';

        [$command, $env] = match ($config['driver'] ?? null) {
            'mysql', 'mariadb' => [
                ['mysqldump', '--single-transaction', '--quick', '--no-tablespaces', '--host='.$config['host'], '--port='.($config['port'] ?? 3306), '--user='.$config['username'], $config['database']],
                ['MYSQL_PWD' => (string) $config['password']],
            ],
            'pgsql' => [
                ['pg_dump', '--no-owner', '--host='.$config['host'], '--port='.($config['port'] ?? 5432), '--username='.$config['username'], $config['database']],
                ['PGPASSWORD' => (string) $config['password']],
            ],
            default => [null, []],
        };
        if ($command === null) {
            $this->error("Backups are not supported for driver [{$config['driver']}].");

            return self::FAILURE;
        }

        $process = new Process($command, base_path(), $env + ['PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin'], null, 1800);
        $process->run();
        if (! $process->isSuccessful()) {
            $this->error('Dump failed: '.trim($process->getErrorOutput()));

            return self::FAILURE;
        }
        File::put($file, gzencode($process->getOutput(), 6));

        $marker = (string) config('platform.backup_marker') ?: $dir.'/last-success';
        File::ensureDirectoryExists(dirname($marker), 0750);
        File::put($marker, now()->toIso8601String());

        $keep = max(1, (int) $this->option('keep'));
        collect(File::files($dir))
            ->filter(fn ($f) => str_starts_with($f->getFilename(), 'db-'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->slice($keep)
            ->each(fn ($f) => File::delete($f->getPathname()));

        $this->info('Backup written: '.basename($file));

        return self::SUCCESS;
    }
}
