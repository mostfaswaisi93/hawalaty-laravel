<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->name(),
            'phone' => null,
        ];
    }
}
