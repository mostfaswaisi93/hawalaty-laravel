<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\BankName;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankNameFactory extends Factory
{
    protected $model = BankName::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'active' => true,
        ];
    }
}
