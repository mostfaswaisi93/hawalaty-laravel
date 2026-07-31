<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\WalletName;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletName>
 */
class WalletNameFactory extends Factory
{
    protected $model = WalletName::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'active' => true,
        ];
    }
}
