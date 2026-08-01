<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\AccountType;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountTypeFactory extends Factory
{
    protected $model = AccountType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'active' => true,
        ];
    }
}
