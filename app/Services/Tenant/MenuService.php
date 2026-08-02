<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Enums\TaxCategory;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\PriceChange;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class MenuService
{
    public function listCategories(): Collection
    {
        return MenuCategory::query()->orderBy('position')->get();
    }

    public function createCategory(array $data): MenuCategory
    {
        return DB::connection('tenant')->transaction(function () use ($data) {
            $nextPosition = ((int) MenuCategory::lockForUpdate()->max('position')) + 1;

            $category = MenuCategory::create([
                'name' => $data['name'],

                'icon' => filled($data['icon'] ?? null) ? $data['icon'] : null,
                'position' => $nextPosition,
            ]);

            AuditLogger::record(AuditAction::Created, AuditEntityType::MenuItem, $category->name, AuditCategory::Menu);

            return $category;
        });
    }

    public function deleteCategory(MenuCategory $category): void
    {
        $name = $category->name;
        $category->delete();

        AuditLogger::record(AuditAction::Deleted, AuditEntityType::MenuItem, $name, AuditCategory::Menu);
    }

    public function listItems(?string $search = null): Collection
    {
        return MenuItem::query()->with('menuCategory')->whereSearch('name', $search)->get();
    }

    public function createItem(array $data): MenuItem
    {
        $rate = (float) ($data['taxRate'] ?? 0);

        $item = MenuItem::create([
            'menu_category_id' => $data['menuCategoryId'],
            'name' => $data['name'],
            'icon' => filled($data['icon'] ?? null) ? $data['icon'] : null,
            'price' => $data['price'],
            'tax_rate' => $rate,

            // Inferred when nobody has said, so an invoice never carries a blank
            // category - the standard has no default for it.
            'tax_category' => $data['taxCategory'] ?? TaxCategory::inferFrom($rate)->value,
            'tax_included' => $data['taxIncluded'] ?? true,
            'active' => $data['active'] ?? true,
        ]);

        $item->load('menuCategory');

        $this->recordPrice($item, null, (float) $item->price);

        AuditLogger::record(AuditAction::Created, AuditEntityType::MenuItem, $item->name, AuditCategory::Menu);

        return $item;
    }

    public function updateItem(MenuItem $item, array $data): MenuItem
    {
        $update = [];

        if (array_key_exists('name', $data)) {
            $update['name'] = $data['name'];
        }
        if (array_key_exists('menuCategoryId', $data)) {
            $update['menu_category_id'] = $data['menuCategoryId'];
        }
        if (array_key_exists('icon', $data)) {
            $update['icon'] = filled($data['icon']) ? $data['icon'] : null;
        }
        if (array_key_exists('price', $data)) {
            $update['price'] = $data['price'];
        }
        if (array_key_exists('taxRate', $data)) {
            $update['tax_rate'] = (float) ($data['taxRate'] ?? 0);
        }
        if (array_key_exists('taxCategory', $data)) {
            $update['tax_category'] = $data['taxCategory']
                ?: TaxCategory::inferFrom((float) ($data['taxRate'] ?? $item->tax_rate))->value;
        }
        if (array_key_exists('taxIncluded', $data)) {
            $update['tax_included'] = $data['taxIncluded'];
        }
        if (array_key_exists('active', $data)) {
            $update['active'] = $data['active'];
        }

        // Read before the write, or there is nothing left to compare against.
        $previous = (float) $item->price;

        $item->update($update);
        $item->load('menuCategory');

        if (array_key_exists('price', $data) && abs((float) $item->price - $previous) >= 0.0005) {
            $this->recordPrice($item, $previous, (float) $item->price);
        }

        AuditLogger::record(AuditAction::Updated, AuditEntityType::MenuItem, $item->name, AuditCategory::Menu);

        return $item;
    }

    /**
     * Note a price alongside the cost it was chosen against.
     *
     * The cost is the point. Knowing a price was set at 10 says nothing; knowing
     * it was set at 10 when the item cost 6, and the item now costs 9, is the
     * whole margin-erosion report.
     */
    private function recordPrice(MenuItem $item, ?float $old, float $new): void
    {
        PriceChange::create([
            'menu_item_id' => $item->id,
            'old_price' => $old,
            'new_price' => $new,
            'cost_at_change' => (float) $item->cost,
            'team_member_id' => auth('tenant')->id(),
        ]);
    }

    public function deleteItem(MenuItem $item): void
    {
        $name = $item->name;
        $item->delete();

        AuditLogger::record(AuditAction::Deleted, AuditEntityType::MenuItem, $name, AuditCategory::Menu);
    }

    public function exportRows(): array
    {
        return MenuCategory::query()
            ->with(['menuItems' => fn ($query) => $query->orderBy('name')])
            ->orderBy('position')
            ->get()
            ->flatMap(fn (MenuCategory $category) => $category->menuItems->map(fn (MenuItem $item) => [
                'category' => self::sanitizeCsvField($category->name),
                'name' => self::sanitizeCsvField($item->name),
                'price' => (string) $item->price,
                'active' => $item->active ? '1' : '0',
            ]))
            ->all();
    }

    private static function sanitizeCsvField(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    public function importCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return ['created' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $header = array_map(fn ($column) => strtolower(trim((string) $column)), $header);
        $columnIndex = array_flip($header);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::connection('tenant')->transaction(function () use ($handle, $columnIndex, &$created, &$updated, &$skipped) {
            $categoriesByName = MenuCategory::query()->get()->keyBy('name');
            $nextPosition = ((int) MenuCategory::max('position')) + 1;

            $itemsByKey = MenuItem::query()->get()->keyBy(fn (MenuItem $item) => "{$item->menu_category_id}|{$item->name}");

            while (($row = fgetcsv($handle)) !== false) {
                $categoryName = trim((string) ($row[$columnIndex['category'] ?? -1] ?? ''));
                $name = trim((string) ($row[$columnIndex['name'] ?? -1] ?? ''));
                $price = $row[$columnIndex['price'] ?? -1] ?? null;
                $active = $row[$columnIndex['active'] ?? -1] ?? '1';

                if ($categoryName === '' || $name === '' || ! is_numeric($price) || (float) $price < 0) {
                    $skipped++;

                    continue;
                }

                $category = $categoriesByName->get($categoryName);

                if (! $category) {
                    $category = MenuCategory::create(['name' => $categoryName, 'position' => $nextPosition++]);
                    $categoriesByName->put($categoryName, $category);
                }

                $itemKey = "{$category->id}|{$name}";
                $item = $itemsByKey->get($itemKey);
                $attributes = [
                    'price' => $price,
                    'active' => in_array(strtolower((string) $active), ['0', 'false', 'no', ''], true) ? false : true,
                ];

                if ($item) {
                    // A bulk import is the most common way a price moves and the
                    // easiest way to move one by accident, so it is exactly the
                    // path that has to leave a trail.
                    $previous = (float) $item->price;

                    $item->update($attributes);
                    $updated++;

                    if (abs((float) $item->price - $previous) >= 0.0005) {
                        $this->recordPrice($item, $previous, (float) $item->price);
                    }
                } else {
                    $item = MenuItem::create([...$attributes, 'menu_category_id' => $category->id, 'name' => $name]);
                    $itemsByKey->put($itemKey, $item);
                    $created++;

                    $this->recordPrice($item, null, (float) $item->price);
                }
            }
        });

        fclose($handle);

        AuditLogger::record(AuditAction::Updated, AuditEntityType::MenuItem, __('استيراد جماعي'), AuditCategory::Menu);

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }
}
