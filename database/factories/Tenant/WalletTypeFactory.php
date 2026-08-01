<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\WalletType;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletTypeFactory extends Factory
{
    protected $model = WalletType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'active' => true,
        ];
    }
}
