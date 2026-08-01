<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
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
