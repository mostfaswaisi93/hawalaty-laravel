<?php

namespace App\Console\Commands;

use App\Enums\LicenseStatus;
use App\Models\ActivityLog;
use App\Models\License;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `php artisan licenses:expire-overdue` - see BACKEND_PROMPT.md section 11,
 * "انتهاء تلقائي مجدوَل". Registered daily in routes/console.php.
 */
class ExpireLicenses extends Command
{
    protected $signature = 'licenses:expire-overdue';

    protected $description = 'Flip every trial/active license whose expired_at has passed to expired, logging each to ActivityLog.';

    public function handle(): int
    {
        // `where`, not `whereDate`: `expired_at` is already a DATE column, so
        // wrapping it in DATE() changes no value but does stop the index range
        // from narrowing. Both plans still NAME licenses_status_expired_at_index
        // - `status` alone can drive it - so `key` and `possible_keys` look
        // identical and hide the difference. key_len is the tell: 4 with the
        // bare column (status 1 byte + date 3), 1 with DATE() wrapped around it,
        // meaning every row of the matching statuses is read and the date is
        // filtered afterwards.
        //
        // startOfDay(), not toDateString(): the `date` cast writes the column
        // as `Y-m-d H:i:s`, so on a driver that compares these as text (sqlite,
        // which the test suite uses) a bare `Y-m-d` string sorts BELOW every
        // stored value and the predicate silently matches nothing. MySQL coerces
        // and hides it; the two drivers have to agree.
        //
        // `<`, not `<=`: a licence is valid THROUGH its expiry date. Expiring it
        // on the morning of the date the customer paid for takes a day off every
        // subscription sold.
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
