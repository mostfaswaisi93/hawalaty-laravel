<?php

namespace Database\Seeders;

use App\Models\License;
use App\Models\Package;
use App\Services\TenantProvisioner;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Provisions the ONE working tenant, and nothing else.
 *
 * This is the default `db:seed` path: a real License + physical MySQL database
 * + user, the tenant migrations, and the baseline permissions/roles/owner that
 * TenantProvisioner writes - then it stops. No catalogue, no orders, no
 * expenses. The workspace is meant to be filled with real data through the app.
 *
 * Sample data lives in DemoTenantSeeder and PlatformDemoSeeder, which are NOT
 * called from DatabaseSeeder. They are opt-in:
 *
 *     php artisan db:seed --class=DemoTenantSeeder     # fills this tenant
 *     php artisan db:seed --class=PlatformDemoSeeder   # fills the admin console
 *
 * Keeping them out of the default run is deliberate: a fresh install that
 * silently invents 59 orders and a four-month trading history is impossible to
 * tell apart from real usage once someone starts entering their own.
 *
 * Idempotent - guarded on the business name, so `db:seed` is safe to re-run.
 */
class TenantSeeder extends Seeder
{
    public const BUSINESS_NAME = 'بيت التميس';

    public const OWNER_NAME = 'مصطفى سويسي';

    public const OWNER_EMAIL = 'owner@hawalaty.test';

    public const OWNER_PASSWORD = 'password';

    public function run(): void
    {
        $license = License::where('customer_name', self::BUSINESS_NAME)->first();

        if ($license) {
            $this->command->info('Tenant «'.self::BUSINESS_NAME."» already exists (license {$license->license_code}).");
            $this->printCredentials($license->license_code);

            return;
        }

        // Prefer the richest package so no feature is gated off while working;
        // falls back to whatever the catalogue has if the names ever change.
        $package = Package::where('name', 'Enterprise')->first()
            ?? Package::orderByDesc('price')->first();

        if (! $package) {
            $this->command->warn('No package found - PackageSeeder must run first. Skipping tenant.');

            return;
        }

        try {
            $license = app(TenantProvisioner::class)->provision([
                'businessName' => self::BUSINESS_NAME,
                'ownerName' => self::OWNER_NAME,
                'ownerEmail' => self::OWNER_EMAIL,
                'phone' => '0599123456',
                'password' => self::OWNER_PASSWORD,
                'packageId' => $package->id,
            ]);
        } catch (Throwable $e) {
            $this->command->error('Failed to provision the tenant: '.$e->getMessage());
            $this->command->warn('The DB user in your .env needs CREATE DATABASE / CREATE USER / GRANT privileges (Laragon root has them).');

            return;
        }

        $this->command->info('Provisioned «'.self::BUSINESS_NAME."» on the {$package->name} package.");
        $this->printCredentials($license->license_code);
    }

    private function printCredentials(string $code): void
    {
        $this->command->newLine();
        $this->command->getOutput()->writeln(' <bg=blue;fg=white> بيانات الدخول / LOGIN </>');
        $this->command->getOutput()->writeln(" <fg=gray>رمز الترخيص / License code :</> <options=bold>{$code}</>");
        $this->command->getOutput()->writeln(' <fg=gray>البريد / Email             :</> <options=bold>'.self::OWNER_EMAIL.'</>');
        $this->command->getOutput()->writeln(' <fg=gray>كلمة المرور / Password     :</> <options=bold>'.self::OWNER_PASSWORD.'</>');
        $this->command->newLine();
    }
}
