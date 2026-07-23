<?php

namespace Database\Factories\Tenant;

use App\Enums\PermissionKey;
use App\Models\Tenant\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        return [
            'key' => fake()->randomElement(PermissionKey::cases()),
        ];
    }
}
