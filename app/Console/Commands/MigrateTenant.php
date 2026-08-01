<?php

namespace App\Console\Commands;

use App\Enums\LicenseStatus;
use App\Exceptions\InvalidLicenseException;
use App\Models\License;
use App\Services\TenantConnectionResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * `php artisan tenants:migrate {code}` / `--all`. Runs database/migrations/tenant,
 * which plain `artisan migrate` does NOT pick up.
 *
 * Safe to call mid-provisioning: TenantConnectionResolver only needs a
 * `DatabaseConnection` row, not tables on the other end.
 */
class MigrateTenant extends Command
{
    protected $signature = 'tenants:migrate {code? : A single license_code to migrate} {--all : Migrate every active/trial license that has a database connection}';

    protected $description = "Run the tenant migrations (database/migrations/tenant) against one or every tenant's database.";

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->migrateAll();
        }

        $code = $this->argument('code');

        if (! $code) {
            $this->error('Provide a license code, or use --all to migrate every active/trial tenant.');

            return self::FAILURE;
        }

        try {
            $this->migrateOne($code);
        } catch (InvalidLicenseException $e) {
            $this->error("Could not resolve license \"{$code}\": {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Tenant migrations complete for {$code}.");

        return self::SUCCESS;
    }

    /**
     * Loop every active/trial license that has a DatabaseConnection, running
     * migrations for each. A single tenant's failure is caught and reported
     * (not thrown) so one bad database doesn't stop the rest.
     */
    protected function migrateAll(): int
    {
        $licenses = License::whereIn('status', [LicenseStatus::Active, LicenseStatus::Trial])
            ->whereHas('databaseConnection')
            ->get();

        $succeeded = 0;
        $failed = 0;

        foreach ($licenses as $license) {
            try {
                $this->migrateOne($license->license_code);
                $this->info("[OK]   {$license->license_code} ({$license->customer_name})");
                $succeeded++;
            } catch (Throwable $e) {
                $this->error("[FAIL] {$license->license_code} ({$license->customer_name}): {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. {$succeeded} succeeded, {$failed} failed, out of {$licenses->count()} eligible licenses.");

        return self::SUCCESS;
    }

    /**
     * @throws InvalidLicenseException
     */
    protected function migrateOne(string $licenseCode): void
    {
        TenantConnectionResolver::resolve($licenseCode);

        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--force' => true,
        ]);
    }
}
