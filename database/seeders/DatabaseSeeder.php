<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * The default run is deliberately MINIMAL: the platform admin, the package
     * catalogue, and one provisioned tenant to work in. Nothing invents
     * business data.
     *
     * Order matters - the admin and the packages must exist before TenantSeeder
     * provisions anything, because License.package_id is a required FK.
     *
     * Sample data is opt-in, never part of this run:
     *
     *     php artisan db:seed --class=DemoTenantSeeder     # catalogue, team, orders, expenses
     *     php artisan db:seed --class=PlatformDemoSeeder   # boxes, partners, payments, clearing
     *
     * Every step is idempotent, so `db:seed` can be re-run safely.
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
            TenantSeeder::class,
        ]);
    }
}
