<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'description' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 5, 500),
            'bank_id' => null,
            'date' => fake()->date(),
        ];
    }
}
