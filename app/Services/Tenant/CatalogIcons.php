<?php

namespace App\Services\Tenant;

/**
 * The icons a tenant may pick for a catalogue entry or category.
 *
 * A WHITELIST, not free text. Two reasons: the value is rendered straight into a
 * `<span class="material-symbols-outlined">` as a ligature, so an arbitrary
 * string would be a way to put content on the page; and an unrecognised
 * ligature renders as its own literal text, which would look broken rather than
 * simply wrong.
 *
 * Grouped by trade so the picker is scannable - the whole point of this feature
 * is that the tenant is not necessarily a restaurant.
 */
class CatalogIcons
{
    /**
     * @return array<string, array<int, string>> group label => Material Symbols names
     */
    public function groups(): array
    {
        return [
            'عام' => ['category', 'inventory_2', 'label', 'sell', 'shopping_bag', 'storefront', 'list_alt', 'shelves'],
            'مطاعم ومقاهي' => ['restaurant', 'restaurant_menu', 'lunch_dining', 'local_pizza', 'bakery_dining', 'local_cafe', 'coffee', 'icecream', 'kebab_dining', 'ramen_dining'],
            'بقالة وفاكهة' => ['nutrition', 'grocery', 'egg', 'set_meal', 'liquor', 'water_drop'],
            'محروقات وسيارات' => ['local_gas_station', 'ev_station', 'oil_barrel', 'directions_car', 'two_wheeler', 'build', 'car_repair', 'tire_repair'],
            // Added because the picker had nothing for these trades at all: a
            // bookshop or a tobacconist could only reach for the generic box.
            'مكتبات وقرطاسية' => ['menu_book', 'book_2', 'auto_stories', 'edit', 'draw', 'print', 'newspaper', 'backpack'],
            'تبغ ودخان' => ['smoking_rooms', 'local_fire_department', 'air'],
            'ملابس وإكسسوارات' => ['checkroom', 'apparel', 'watch', 'diamond', 'footprint'],
            'إلكترونيات وهواتف' => ['smartphone', 'devices', 'headphones', 'memory', 'cable', 'sim_card'],
            'صحة وصيدليات' => ['medical_services', 'pill', 'vaccines', 'health_and_beauty'],
            'خدمات ومنوعات' => ['home_repair_service', 'cleaning_services', 'local_laundry_service', 'pets', 'content_cut', 'local_florist', 'toys', 'sports_soccer'],
        ];
    }

    /** @return array<int, string> */
    public function all(): array
    {
        return array_merge(...array_values($this->groups()));
    }

    public function isValid(?string $icon): bool
    {
        return $icon !== null && in_array($icon, $this->all(), true);
    }

    /**
     * The glyph to draw, falling back through entry -> category -> neutral, so a
     * catalogue that has never touched icons still looks deliberate.
     */
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
