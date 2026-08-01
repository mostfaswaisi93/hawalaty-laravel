<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'key' => null,
            'name' => fake()->unique()->jobTitle(),
            'is_system' => false,
        ];
    }
}
