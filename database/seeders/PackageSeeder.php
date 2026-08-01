<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

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
