<?php

namespace App\Console\Commands;

use App\Services\Admin\BackupService;
use Illuminate\Console\Command;

/**
 * `php artisan backups:scheduled` — see BACKEND_PROMPT.md section 6/11.
 * Registered daily at 02:00 in routes/console.php.
 *
 * Scope decision: backs up the PLATFORM database only (license = null), not
 * every tenant's database in a loop. Looping every active license was
 * considered (and the code would be a one-line change — call
 * BackupService::runScheduled($license) per License::whereIn(['active',
 * 'trial'])->get()) but was left out of this task's scope: for a real
 * multi-tenant install with many licenses this would need staggering/queueing
 * so it doesn't run 50 mysqldumps back-to-back at 02:00, which is a bigger
 * design decision than this task covers. Documented here for a future pass.
 */
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
