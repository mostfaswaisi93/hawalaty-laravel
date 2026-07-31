<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\CashBox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashBox>
 */
class CashBoxFactory extends Factory
{
    protected $model = CashBox::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'active' => true,
        ];
    }
}
