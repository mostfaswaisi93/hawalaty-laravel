<?php

namespace App\Services\Tenant;

class CatalogIcons
{
    public function groups(): array
    {
        return [
            'عام' => ['category', 'inventory_2', 'label', 'sell', 'shopping_bag', 'storefront', 'list_alt', 'shelves'],
            'مطاعم ومقاهي' => ['restaurant', 'restaurant_menu', 'lunch_dining', 'local_pizza', 'bakery_dining', 'local_cafe', 'coffee', 'icecream', 'kebab_dining', 'ramen_dining'],
            'بقالة وفاكهة' => ['nutrition', 'grocery', 'egg', 'set_meal', 'liquor', 'water_drop'],
            'محروقات وسيارات' => ['local_gas_station', 'ev_station', 'oil_barrel', 'directions_car', 'two_wheeler', 'build', 'car_repair', 'tire_repair'],

            'مكتبات وقرطاسية' => ['menu_book', 'book_2', 'auto_stories', 'edit', 'draw', 'print', 'newspaper', 'backpack'],
            'تبغ ودخان' => ['smoking_rooms', 'local_fire_department', 'air'],
            'ملابس وإكسسوارات' => ['checkroom', 'apparel', 'watch', 'diamond', 'footprint'],
            'إلكترونيات وهواتف' => ['smartphone', 'devices', 'headphones', 'memory', 'cable', 'sim_card'],
            'صحة وصيدليات' => ['medical_services', 'pill', 'vaccines', 'health_and_beauty'],
            'خدمات ومنوعات' => ['home_repair_service', 'cleaning_services', 'local_laundry_service', 'pets', 'content_cut', 'local_florist', 'toys', 'sports_soccer'],
        ];
    }

    public function all(): array
    {
        return array_merge(...array_values($this->groups()));
    }

    public function isValid(?string $icon): bool
    {
        return $icon !== null && in_array($icon, $this->all(), true);
    }

    public function resolve(?string $icon, ?string $fallback = null): string
    {
        if ($this->isValid($icon)) {
            return $icon;
        }

        if ($this->isValid($fallback)) {
            return $fallback;
        }

        return 'category';
    }
}
