<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
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
        $item = MenuItem::create([
            'menu_category_id' => $data['menuCategoryId'],
            'name' => $data['name'],
            'icon' => filled($data['icon'] ?? null) ? $data['icon'] : null,
            'price' => $data['price'],
            'active' => $data['active'] ?? true,
        ]);

        $item->load('menuCategory');

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
        if (array_key_exists('active', $data)) {
            $update['active'] = $data['active'];
        }

        $item->update($update);
        $item->load('menuCategory');

        AuditLogger::record(AuditAction::Updated, AuditEntityType::MenuItem, $item->name, AuditCategory::Menu);

        return $item;
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
                    $item->update($attributes);
                    $updated++;
                } else {
                    $item = MenuItem::create([...$attributes, 'menu_category_id' => $category->id, 'name' => $name]);
                    $itemsByKey->put($itemKey, $item);
                    $created++;
                }
            }
        });

        fclose($handle);

        AuditLogger::record(AuditAction::Updated, AuditEntityType::MenuItem, __('استيراد جماعي'), AuditCategory::Menu);

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }
}
