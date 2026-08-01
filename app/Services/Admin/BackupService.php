<?php

namespace App\Services\Admin;

use App\Enums\BackupStatus;
use App\Enums\BackupTrigger;
use App\Models\Admin;
use App\Models\Backup;
use App\Models\License;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

class BackupService
{
    public function runManual(?License $license, Admin $actor): Backup
    {
        return $this->run($license, BackupTrigger::Manual, $actor->name);
    }

    public function runScheduled(?License $license = null): Backup
    {
        return $this->run($license, BackupTrigger::Scheduled, '');
    }

    protected function run(?License $license, BackupTrigger $trigger, string $actorName): Backup
    {
        $backup = Backup::create([
            'license_id' => $license?->id,
            'trigger' => $trigger,
            'actor_name' => $actorName,
            'status' => BackupStatus::InProgress,
        ]);

        try {
            $credentials = $this->credentialsFor($license);

            $ulid = (string) Str::ulid();
            $relativePath = "backups/{$ulid}.sql.gz";
            $absolutePath = storage_path('app/'.$relativePath);

            File::ensureDirectoryExists(dirname($absolutePath));

            $env = ['MYSQL_PWD' => $credentials['password']];
            $systemRoot = getenv('SystemRoot') ?: getenv('SYSTEMROOT') ?: 'C:\\Windows';
            $env['SystemRoot'] = $systemRoot;
            $env['SYSTEMROOT'] = $systemRoot;

            $result = Process::env($env)
                ->timeout(300)
                ->run([
                    config('backup.mysqldump_binary'),
                    "--host={$credentials['host']}",
                    "--port={$credentials['port']}",
                    "--user={$credentials['username']}",
                    '--single-transaction',
                    '--routines',
                    '--skip-comments',
                    $credentials['database'],
                ]);

            if (! $result->successful()) {
                Log::error('Backup mysqldump failed.', [
                    'backupId' => $backup->id,
                    'licenseId' => $license?->id,
                    'exitCode' => $result->exitCode(),
                    'stderr' => $result->errorOutput(),
                ]);

                $backup->update(['status' => BackupStatus::Failed]);

                return $backup;
            }

            $gzipped = gzencode($result->output(), 9);
            file_put_contents($absolutePath, $gzipped);

            $sizeMb = max(1, (int) ceil(filesize($absolutePath) / 1024 / 1024));

            $backup->update([
                'status' => BackupStatus::Completed,
                'size_mb' => $sizeMb,
                'file_path' => $relativePath,
            ]);

            return $backup->fresh();
        } catch (Throwable $e) {
            Log::error('Backup run threw an unexpected exception.', [
                'backupId' => $backup->id,
                'licenseId' => $license?->id,
                'exception' => $e,
            ]);

            $backup->update(['status' => BackupStatus::Failed]);

            return $backup;
        }
    }

    protected function credentialsFor(?License $license): array
    {
        if ($license) {
            $connection = $license->databaseConnection;

            if (! $connection) {
                throw new \RuntimeException("License {$license->license_code} has no database_connections row.");
            }

            return [
                'host' => $connection->host,
                'port' => $connection->port,
                'database' => $connection->database_name,
                'username' => $connection->username,
                'password' => $connection->password,
            ];
        }

        $platform = config('database.connections.mysql');

        return [
            'host' => $platform['host'],
            'port' => $platform['port'],
            'database' => $platform['database'],
            'username' => $platform['username'],
            'password' => $platform['password'],
        ];
    }
}
