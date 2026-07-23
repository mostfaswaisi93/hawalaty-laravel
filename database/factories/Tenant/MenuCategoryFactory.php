<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuCategory>
 */
class MenuCategoryFactory extends Factory
{
    protected $model = MenuCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'position' => fn () => (int) MenuCategory::max('position') + 1,
        ];
    }
}
