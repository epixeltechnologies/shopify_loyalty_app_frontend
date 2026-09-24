<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * OPS: `php artisan db:backup` — see docs/BACKUP_AND_RECOVERY.md for
 * the full strategy this is one piece of. Runs `mysqldump`, optionally
 * GPG-encrypts the output (recommended for anything leaving the
 * server), and stores the result on a configured disk — by default
 * `backups` (see config/filesystems.php), which should point at
 * off-site storage (S3 or equivalent) in production, never the same
 * disk/server as the database itself.
 *
 * IMPORTANT — HONESTY ABOUT WHAT THIS COMMAND IS AND ISN'T:
 *   - This is the MECHANISM, not a configured/scheduled/tested backup
 *     system. It has not been run against a real production database in
 *     this environment. See docs/BACKUP_AND_RECOVERY.md for what still
 *     needs to happen before this can be considered a working backup
 *     strategy: scheduling (cron or Laravel's scheduler), off-site
 *     storage credentials, GPG key provisioning, and — most
 *     importantly — an actual restore test (a backup that has never
 *     been restored is not a verified backup).
 *   - Never writes credentials into the backup file itself or logs —
 *     `DB_PASSWORD` is passed via a temporary, permissioned my.cnf-style
 *     options file (never as a plain CLI argument, which would be
 *     visible to any other process via `ps`), removed immediately after.
 */
class BackupDatabaseCommand extends Command
{
    protected $signature = 'db:backup {--encrypt : GPG-encrypt the dump using MYSQL_BACKUP_GPG_RECIPIENT} {--disk=backups : Filesystem disk to store the backup on}';

    protected $description = 'Create a MySQL database backup and store it on the configured disk.';

    public function handle(): int
    {
        $database = config('database.connections.mysql.database');
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        $timestamp = now()->format('Y-m-d_His');
        $filename = "backup_{$database}_{$timestamp}.sql";
        $localPath = storage_path("app/tmp/{$filename}");

        if (! is_dir(dirname($localPath))) {
            mkdir(dirname($localPath), 0700, true);
        }

        // Credentials via a temporary options file (mode 0600, deleted
        // immediately after) — never as a CLI argument (visible to any
        // other user's `ps aux` on a shared host) and never logged.
        $optionsFile = tempnam(sys_get_temp_dir(), 'mysqldump_opts');
        file_put_contents($optionsFile, "[client]\nhost={$host}\nport={$port}\nuser={$username}\npassword={$password}\n");
        chmod($optionsFile, 0600);

        $this->info("Creating backup: {$filename}");

        $result = Process::run("mysqldump --defaults-extra-file={$optionsFile} --single-transaction --quick --routines {$database} > {$localPath}");

        unlink($optionsFile);

        if (! $result->successful()) {
            $this->error('mysqldump failed. See logs for details.');
            Log::channel('security')->error('database_backup_failed', ['exit_code' => $result->exitCode()]);

            return self::FAILURE;
        }

        if ($this->option('encrypt')) {
            $recipient = config('backup.gpg_recipient');
            if (! $recipient) {
                $this->error('--encrypt was requested but no GPG recipient is configured (BACKUP_GPG_RECIPIENT). Aborting rather than storing an unencrypted backup.');

                return self::FAILURE;
            }

            $encryptedPath = "{$localPath}.gpg";
            $encryptResult = Process::run("gpg --batch --yes --recipient {$recipient} --output {$encryptedPath} --encrypt {$localPath}");

            if (! $encryptResult->successful()) {
                $this->error('GPG encryption failed. See logs for details.');
                unlink($localPath);

                return self::FAILURE;
            }

            unlink($localPath); // never leave the unencrypted dump on disk once the encrypted copy exists
            $localPath = $encryptedPath;
            $filename .= '.gpg';
        }

        $disk = Storage::disk($this->option('disk'));
        $disk->put($filename, file_get_contents($localPath));
        unlink($localPath);

        $sizeBytes = $disk->size($filename);
        $this->info("Backup stored: {$filename} ({$this->formatBytes($sizeBytes)}) on disk [{$this->option('disk')}]");

        Log::channel('security')->info('database_backup_completed', [
            'filename' => $filename,
            'disk' => $this->option('disk'),
            'size_bytes' => $sizeBytes,
            'encrypted' => $this->option('encrypt'),
        ]);

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1_073_741_824 => round($bytes / 1_073_741_824, 2).' GB',
            $bytes >= 1_048_576 => round($bytes / 1_048_576, 2).' MB',
            default => round($bytes / 1024, 2).' KB',
        };
    }
}
