<?php

namespace App\Console\Commands;

use App\Enums\LicenseStatus;
use App\Models\ActivityLog;
use App\Models\License;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireLicenses extends Command
{
    protected $signature = 'licenses:expire-overdue';

    protected $description = 'Flip every trial/active license whose expired_at has passed to expired, logging each to ActivityLog.';

    public function handle(): int
    {
        $licenses = License::whereIn('status', [LicenseStatus::Active, LicenseStatus::Trial])
            ->where('expired_at', '<', now()->startOfDay())
            ->get();

        foreach ($licenses as $license) {
            DB::transaction(function () use ($license) {
                $license->update(['status' => LicenseStatus::Expired]);

                ActivityLog::create([
                    'admin_id' => null,
                    'action' => 'expired',
                    'entity_type' => 'license',
                    'entity_label' => $license->customer_name,
                ]);
            });

            $this->info("Expired {$license->license_code} ({$license->customer_name}), was due {$license->expired_at->toDateString()}.");
        }

        $this->info("Done. {$licenses->count()} license(s) expired.");

        return self::SUCCESS;
    }
}
