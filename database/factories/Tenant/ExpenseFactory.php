<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Account;
use App\Models\Tenant\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'description' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 5, 500),

            'account_id' => Account::factory(),
            'date' => fake()->date(),
        ];
    }
}
