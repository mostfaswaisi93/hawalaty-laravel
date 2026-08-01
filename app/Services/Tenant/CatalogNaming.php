<?php

namespace App\Services\Tenant;

use App\Models\Tenant\TenantSetting;
use Throwable;

/**
 * What this tenant calls its catalogue: the section, one grouping, one entry.
 *
 * The same software runs a bakery, a fuel station and a bookshop, so every one
 * of those words is the tenant's to choose. Resolved once per request and falls
 * back to the defaults if the table is unreachable.
 */
class CatalogNaming
{
    public const CATALOG = 'catalog_label';

    public const CATEGORY = 'catalog_category_label';

    public const ITEM = 'catalog_item_label';

    public const ICON = 'catalog_icon';

    /** Trade-neutral, unlike the old `restaurant_menu` default. */
    public const DEFAULT_ICON = 'category';

    /** @var array<string, string>|null */
    private ?array $resolved = null;

    /** @return array<string, string> */
    public function defaults(): array
    {
        return [
            self::CATALOG => __('قائمة الأصناف'),
            self::CATEGORY => __('فئة'),
            self::ITEM => __('صنف'),
        ];
    }

    /**
     * Shortcuts for the naming dialog, each paired with its trade's icon. Free
     * text stays available - this is a shortcut, not a whitelist.
     *
     * @return array<int, array{label: string, icon: string}>
     */
    public function catalogPresets(): array
    {
        return [
            ['label' => __('قائمة الطعام'), 'icon' => 'restaurant_menu'],
            ['label' => __('المخبوزات'), 'icon' => 'bakery_dining'],
            ['label' => __('المحروقات'), 'icon' => 'local_gas_station'],
            ['label' => __('الخضار والفواكه'), 'icon' => 'nutrition'],
            ['label' => __('البقالة'), 'icon' => 'storefront'],
            ['label' => __('الكتب والقرطاسية'), 'icon' => 'menu_book'],
            ['label' => __('السجائر والتبغ'), 'icon' => 'smoking_rooms'],
            ['label' => __('الملابس'), 'icon' => 'checkroom'],
            ['label' => __('الإلكترونيات'), 'icon' => 'smartphone'],
            ['label' => __('الأدوية'), 'icon' => 'pill'],
            ['label' => __('قطع الغيار'), 'icon' => 'build'],
            ['label' => __('الخدمات'), 'icon' => 'home_repair_service'],
        ];
    }

    /** @return array<int, string> */
    public function categoryPresets(): array
    {
        return [__('فئة'), __('نوع'), __('قسم'), __('مجموعة'), __('تصنيف')];
    }

    /** @return array<int, string> */
    public function itemPresets(): array
    {
        return [__('صنف'), __('منتج'), __('خدمة'), __('سلعة'), __('عنصر')];
    }

    public function catalog(): string
    {
        return $this->get(self::CATALOG);
    }

    public function category(): string
    {
        return $this->get(self::CATEGORY);
    }

    public function item(): string
    {
        return $this->get(self::ITEM);
    }

    public function icon(): string
    {
        $icon = $this->all()[self::ICON] ?? null;

        return filled($icon) ? $icon : self::DEFAULT_ICON;
    }

    /**
     * Whether the owner has named the section. It stays out of the navigation
     * until then - only an explicit label counts, never a default.
     */
    public function isConfigured(): bool
    {
        return filled($this->all()[self::CATALOG] ?? null);
    }

    /**
     * The stored value with NO fallback. Forms need this: pre-filling a field
     * with the default would persist the placeholder as a real choice.
     */
    public function raw(string $key): ?string
    {
        $value = $this->all()[$key] ?? null;

        return filled($value) ? $value : null;
    }

    public function get(string $key): string
    {
        $defaults = $this->defaults();
        $value = $this->all()[$key] ?? null;

        return filled($value) ? $value : ($defaults[$key] ?? '');
    }

    /** @return array<string, string> */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        try {
            // ICON is listed explicitly - it has no textual default, so keying
            // this off defaults() alone never loaded it.
            return $this->resolved = TenantSetting::query()
                ->whereIn('key', [...array_keys($this->defaults()), self::ICON])
                ->pluck('value', 'key')
                ->all();
        } catch (Throwable) {
            // No tenant connection yet; defaults keep every page rendering.
            return $this->resolved = [];
        }
    }

    /**
     * @param  array<string, string|null>  $labels
     */
    public function put(array $labels): void
    {
        foreach ($labels as $key => $value) {
            // ICON is writable despite being absent from defaults().
            if ($key !== self::ICON && ! array_key_exists($key, $this->defaults())) {
                continue;
            }

            TenantSetting::updateOrCreate(
                ['key' => $key],
                ['value' => filled($value) ? trim((string) $value) : null],
            );
        }

        $this->resolved = null;
    }
}
