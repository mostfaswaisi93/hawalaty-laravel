<?php

namespace App\Services\Tenant;

use App\Models\Tenant\TenantSetting;
use Throwable;

/**
 * What this tenant calls its catalogue.
 *
 * The app shipped hard-coded as a restaurant - "قائمة الطعام", "فئة", "صنف" -
 * but the same software runs a fruit shop, a fuel station or a hardware store.
 * Those businesses do not sell "أصناف طعام"; they sell fruit, or fuel grades, or
 * parts. Every one of those words is now the tenant's to choose.
 *
 * Three labels, because they appear in different grammatical places:
 *   - `catalog`  the section itself      ("قائمة الطعام" / "أصناف الفاكهة")
 *   - `category` one grouping, singular  ("فئة" / "نوع")
 *   - `item`     one entry, singular     ("صنف" / "منتج")
 *
 * The restaurant wording remains the DEFAULT, so existing tenants see no change
 * until they choose otherwise.
 *
 * Resolved once per request (the labels appear in the sidebar of every page) and
 * degrades to the defaults if the table is unreachable - a missing label must
 * never take a page down.
 */
class CatalogNaming
{
    public const CATALOG = 'catalog_label';

    public const CATEGORY = 'catalog_category_label';

    public const ITEM = 'catalog_item_label';

    /** @var array<string, string>|null */
    private ?array $resolved = null;

    /** @return array<string, string> */
    public function defaults(): array
    {
        return [
            self::CATALOG => __('قائمة الطعام'),
            self::CATEGORY => __('فئة'),
            self::ITEM => __('صنف'),
        ];
    }

    /** The section's own name, e.g. "قائمة الطعام" or "أصناف الفاكهة". */
    public function catalog(): string
    {
        return $this->get(self::CATALOG);
    }

    /** One grouping, singular: "فئة" / "نوع". */
    public function category(): string
    {
        return $this->get(self::CATEGORY);
    }

    /** One entry, singular: "صنف" / "منتج" / "خدمة". */
    public function item(): string
    {
        return $this->get(self::ITEM);
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
            return $this->resolved = TenantSetting::query()
                ->whereIn('key', array_keys($this->defaults()))
                ->pluck('value', 'key')
                ->all();
        } catch (Throwable) {
            // No tenant connection, or the table has not been migrated yet.
            // Falling back to the defaults keeps every page rendering.
            return $this->resolved = [];
        }
    }

    /**
     * @param  array<string, string|null>  $labels
     */
    public function put(array $labels): void
    {
        foreach ($labels as $key => $value) {
            if (! array_key_exists($key, $this->defaults())) {
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
