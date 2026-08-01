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
 * The DEFAULTS are trade-neutral. "قائمة الطعام" used to be the fallback, which
 * meant a brand-new account was told it ran a restaurant before it had entered
 * anything - a fuel station signing up saw a food menu in its sidebar. The
 * generic wording says what the section IS (a list of the things you sell)
 * without naming a trade, and any tenant that wants "قائمة الطعام" back types it
 * once in /constants.
 *
 * Renaming the fallback changes nothing that was explicitly chosen: a tenant who
 * already saved a label has a row in `tenant_settings`, and that row still wins.
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
            // "قائمة الأصناف", not "قائمة الطعام": neutral across a bakery, a
            // fuel station and a workshop alike. `فئة`/`صنف` were already
            // trade-neutral and are left as they were.
            self::CATALOG => __('قائمة الأصناف'),
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
