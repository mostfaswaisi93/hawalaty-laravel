<?php

namespace Database\Factories\Tenant;

use App\Enums\BankType;
use App\Models\Tenant\Bank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bank>
 */
class BankFactory extends Factory
{
    protected $model = Bank::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'type' => BankType::Bank,
            'code' => fake()->unique()->lexify('BNK???'),
            'active' => true,
            'phone' => null,
        ];
    }

    public function wallet(): static
    {
        return $this->state(['type' => BankType::Wallet]);
    }
}
