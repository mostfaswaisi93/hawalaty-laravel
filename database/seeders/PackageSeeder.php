<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * Fixed product catalog on the platform DB. Must run before any tenant is
 * provisioned because License.package_id is a non-nullable FK and
 * TenantProvisioner falls back to the cheapest package when none is given.
 * Idempotent via updateOrCreate() keyed on the package name.
 */
class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Basic',
                'price' => 0,
                'device_limit' => 3,
                'features' => ['transfers', 'menu', 'reports'],
            ],
            [
                'name' => 'Pro',
                'price' => 99,
                'device_limit' => 10,
                'features' => ['transfers', 'menu', 'reports', 'banks', 'expenses', 'team', 'whatsapp'],
            ],
            [
                'name' => 'Enterprise',
                'price' => 299,
                'device_limit' => 50,
                'features' => ['transfers', 'menu', 'reports', 'banks', 'expenses', 'team', 'devices', 'constants', 'backups', 'whatsapp'],
            ],
        ];

        foreach ($packages as $package) {
            Package::updateOrCreate(['name' => $package['name']], $package);
        }
    }
}
