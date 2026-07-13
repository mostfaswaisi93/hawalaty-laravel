<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters: the platform admin + package catalog must exist before
     * DemoTenantSeeder provisions a tenant (License.package_id is a required
     * FK, and the demo owner logs into the admin-less tenant space). Every
     * step is idempotent so `db:seed` can be re-run safely.
     */
    public function run(): void
    {
        if (! Admin::where('email', 'admin@hawalaty.test')->exists()) {
            Admin::factory()->create([
                'name' => 'Platform Admin',
                'email' => 'admin@hawalaty.test',
            ]);
        }

        $this->call([
            PackageSeeder::class,
            DemoTenantSeeder::class,
        ]);
    }
}
