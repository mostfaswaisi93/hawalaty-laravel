<?php

namespace App\Services\Tenant;

use App\Models\Tenant\TenantSetting;
use Throwable;

/**
 * What this workspace calls the things it sells.
 *
 * The trade itself is not chosen here - it is a platform-owned property of the
 * license (see {@see \App\Enums\BusinessType}) and is seeded into these settings
 * at provisioning. What remains tenant-editable is only the wording: a pharmacy
 * may prefer "المستحضرات" to "الأدوية" without changing which modules it has.
 */
class CatalogNaming
{
    public const CATALOG = 'catalog_label';

    public const CATEGORY = 'catalog_category_label';

    public const ITEM = 'catalog_item_label';

    public const ICON = 'catalog_icon';

    public const DEFAULT_ICON = 'category';

    private ?array $resolved = null;

    public function defaults(): array
    {
        return [
            self::CATALOG => __('قائمة الأصناف'),
            self::CATEGORY => __('فئة'),
            self::ITEM => __('صنف'),
        ];
    }

    public function categoryPresets(): array
    {
        return [__('فئة'), __('نوع'), __('قسم'), __('مجموعة'), __('تصنيف')];
    }

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

    public function isConfigured(): bool
    {
        return filled($this->all()[self::CATALOG] ?? null);
    }

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

    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        try {
            return $this->resolved = TenantSetting::query()
                ->whereIn('key', [...array_keys($this->defaults()), self::ICON])
                ->pluck('value', 'key')
                ->all();
        } catch (Throwable) {
            return $this->resolved = [];
        }
    }

    public function put(array $labels): void
    {
        foreach ($labels as $key => $value) {
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
