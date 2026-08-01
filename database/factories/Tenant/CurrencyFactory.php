<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->currencyCode(),
            'symbol' => fake()->randomElement(['₪', '$', '€', 'د.أ', '£']),
            'active' => true,
        ];
    }
}
