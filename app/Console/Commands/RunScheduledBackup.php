<?php

namespace App\Console\Commands;

use App\Services\Admin\BackupService;
use Illuminate\Console\Command;

class RunScheduledBackup extends Command
{
    protected $signature = 'backups:scheduled';

    protected $description = 'Run a scheduled mysqldump backup of the platform database.';

    public function handle(BackupService $service): int
    {
        $backup = $service->runScheduled();

        $this->info("Scheduled backup {$backup->id} finished with status: {$backup->status->value}.");

        return $backup->status->value === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
