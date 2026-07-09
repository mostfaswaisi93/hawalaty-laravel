<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * Categories + items live in one Service — they're tightly coupled (an item
 * always belongs to a category, and MenuItemResource denormalizes the
 * category name onto the item), matching the task instructions' explicit
 * pairing.
 */
class MenuService
{
    // --- Categories ---

    public function listCategories(): Collection
    {
        return MenuCategory::query()->orderBy('position')->get();
    }

    /**
     * index/store/destroy only — no update, matching
     * ConstantsService.addMenuCategory()/removeMenuCategory() in the
     * Angular app, which never renames a category, only adds/removes.
     */
    public function createCategory(array $data): MenuCategory
    {
        $nextPosition = ((int) MenuCategory::max('position')) + 1;

        $category = MenuCategory::create([
            'name' => $data['name'],
            'position' => $nextPosition,
        ]);

        AuditLogger::record(AuditAction::Created, AuditEntityType::MenuItem, $category->name, AuditCategory::Menu);

        return $category;
    }

    public function deleteCategory(MenuCategory $category): void
    {
        $name = $category->name;
        $category->delete();

        AuditLogger::record(AuditAction::Deleted, AuditEntityType::MenuItem, $name, AuditCategory::Menu);
    }

    // --- Items ---

    public function listItems(?string $search = null): Collection
    {
        $query = MenuItem::query()->with('menuCategory');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->get();
    }

    public function createItem(array $data): MenuItem
    {
        $item = MenuItem::create([
            'menu_category_id' => $data['menuCategoryId'],
            'name' => $data['name'],
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
}
