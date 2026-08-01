<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Transfer;
use App\Models\Tenant\TransferLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransferLineItemFactory extends Factory
{
    protected $model = TransferLineItem::class;

    public function definition(): array
    {
        return [
            'transfer_id' => Transfer::factory(),
            'menu_item_id' => MenuItem::factory(),
            'name' => fake()->words(2, true),
            'quantity' => fake()->numberBetween(1, 5),
            'price' => fake()->randomFloat(2, 5, 100),
        ];
    }
}
