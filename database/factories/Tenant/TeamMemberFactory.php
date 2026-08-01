<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Role;
use App\Models\Tenant\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class TeamMemberFactory extends Factory
{
    protected $model = TeamMember::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => Role::factory(),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
