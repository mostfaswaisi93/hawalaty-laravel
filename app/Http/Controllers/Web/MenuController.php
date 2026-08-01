<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\MenuCategory\StoreMenuCategoryRequest;
use App\Http\Requests\Tenant\MenuItem\ImportMenuItemsRequest;
use App\Http\Requests\Tenant\MenuItem\StoreMenuItemRequest;
use App\Http\Requests\Tenant\MenuItem\UpdateMenuItemRequest;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Services\Tenant\CatalogIcons;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\MenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MenuController extends Controller
{
    public function __construct(
        private readonly MenuService $service,
        private readonly CatalogIcons $icons,
        private readonly CatalogNaming $naming,
    ) {}

    public function index(): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة القائمة.'));
        }

        $categories = MenuCategory::query()
            ->with(['menuItems' => fn ($query) => $query->orderBy('name')])
            ->orderBy('position')
            ->get();

        return view('menu.index', [
            'categories' => $categories,

            'catalogIcons' => $this->icons,
            'naming' => $this->naming,
        ]);
    }

    public function storeCategory(StoreMenuCategoryRequest $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->createCategory($request->validated());

        return redirect()->route('menu.index')->with('success', __('تمت إضافة الفئة بنجاح.'));
    }

    public function destroyCategory(string $category): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->deleteCategory(MenuCategory::findOrFail($category));

        return redirect()->route('menu.index')->with('success', __('تم حذف الفئة وأصنافها.'));
    }

    public function storeItem(StoreMenuItemRequest $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->createItem($request->validated());

        return redirect()->route('menu.index')->with('success', __('تمت إضافة الصنف بنجاح.'));
    }

    public function updateItem(UpdateMenuItemRequest $request, string $item): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->updateItem(MenuItem::findOrFail($item), $request->validated());

        return redirect()->route('menu.index')->with('success', __('تم تحديث الصنف بنجاح.'));
    }

    public function destroyItem(string $item): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->deleteItem(MenuItem::findOrFail($item));

        return redirect()->route('menu.index')->with('success', __('تم حذف الصنف.'));
    }

    public function export(): StreamedResponse
    {
        abort_unless($this->canManage(), 403);

        $rows = $this->service->exportRows();

        return Response::streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['category', 'name', 'price', 'active']);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'menu-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(ImportMenuItemsRequest $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $result = $this->service->importCsv($request->file('file'));

        return redirect()->route('menu.index')->with('success', __(
            'تم الاستيراد: :created صنف جديد، :updated صنف محدث، :skipped صف متجاهَل.',
            $result
        ));
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::MenuManage);
    }
}
