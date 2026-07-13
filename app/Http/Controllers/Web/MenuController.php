<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\MenuCategory\StoreMenuCategoryRequest;
use App\Http\Requests\Tenant\MenuItem\StoreMenuItemRequest;
use App\Http\Requests\Tenant\MenuItem\UpdateMenuItemRequest;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Services\Tenant\MenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Server-rendered menu management (categories + items). Gated on MenuManage.
 * String ids + manual find keep every tenant-table lookup inside the
 * controller, after the tenant connection is resolved.
 */
class MenuController extends Controller
{
    public function __construct(private readonly MenuService $service) {}

    public function index(): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', 'ليست لديك صلاحية لإدارة القائمة.');
        }

        $categories = MenuCategory::query()
            ->with(['menuItems' => fn ($query) => $query->orderBy('name')])
            ->orderBy('position')
            ->get();

        return view('menu.index', ['categories' => $categories]);
    }

    // --- Categories ---

    public function storeCategory(StoreMenuCategoryRequest $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->createCategory($request->validated());

        return redirect()->route('menu.index')->with('success', 'تمت إضافة الفئة بنجاح.');
    }

    public function destroyCategory(string $category): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->deleteCategory(MenuCategory::findOrFail($category));

        return redirect()->route('menu.index')->with('success', 'تم حذف الفئة وأصنافها.');
    }

    // --- Items ---

    public function storeItem(StoreMenuItemRequest $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->createItem($request->validated());

        return redirect()->route('menu.index')->with('success', 'تمت إضافة الصنف بنجاح.');
    }

    public function updateItem(UpdateMenuItemRequest $request, string $item): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->updateItem(MenuItem::findOrFail($item), $request->validated());

        return redirect()->route('menu.index')->with('success', 'تم تحديث الصنف بنجاح.');
    }

    public function destroyItem(string $item): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->deleteItem(MenuItem::findOrFail($item));

        return redirect()->route('menu.index')->with('success', 'تم حذف الصنف.');
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::MenuManage);
    }
}
