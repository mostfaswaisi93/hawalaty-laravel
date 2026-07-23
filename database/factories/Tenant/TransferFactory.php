<?php

namespace Database\Factories\Tenant;

use App\Enums\PaymentMethod;
use App\Enums\TransferStatus;
use App\Models\Tenant\Transfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transfer>
 */
class TransferFactory extends Factory
{
    protected $model = Transfer::class;

    public function definition(): array
    {
        return [
            'customer' => fake()->name(),
            'status' => TransferStatus::Pending,
            'amount' => fake()->randomFloat(2, 10, 200),
            'item_name' => fake()->words(3, true),
            'payment_method' => PaymentMethod::Cash,
        ];
    }
}
