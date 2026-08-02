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
use App\Services\Tenant\BusinessProfile;
use App\Services\Tenant\CatalogIcons;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\MenuService;
use App\Services\Tenant\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MenuController extends Controller
{
    public function __construct(
        private readonly MenuService $service,
        private readonly CatalogIcons $icons,
        private readonly CatalogNaming $naming,
        private readonly BusinessProfile $business,
        private readonly StockService $stock,
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

    /**
     * The full item record.
     *
     * A page rather than a modal: an item carries thirty-odd fields once the
     * trade needs them, and the sections that matter differ by vertical. The
     * modal on the index stays for the common case - a name, a category and a
     * price - and this is where the rest lives.
     */
    public function editItem(string $item): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة القائمة.'));
        }

        $record = MenuItem::with('menuCategory')->findOrFail($item);

        return view('menu.item', [
            'item' => $record,
            'categories' => $this->service->listCategories(),
            'catalogIcons' => $this->icons,
            'naming' => $this->naming,
            'business' => $this->business,

            // Whether the ledger already has something to say about this item.
            // Several fields stop being editable at that point, and the screen
            // has to explain why rather than silently ignoring the input.
            'onHand' => $record->isStocked() ? $this->stock->onHand($record->id) : 0.0,
            'hasHistory' => $record->movements()->exists(),
        ]);
    }

    public function updateItem(UpdateMenuItemRequest $request, string $item): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $record = MenuItem::findOrFail($item);

        try {
            $this->service->updateItem($record, $request->validated());
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        // Back where they came from: an edit started on the full page should not
        // dump the user on the index and make them find the item again.
        return $request->boolean('fromPage')
            ? redirect()->route('menu.items.edit', $record->id)->with('success', __('تم تحديث الصنف بنجاح.'))
            : redirect()->route('menu.index')->with('success', __('تم تحديث الصنف بنجاح.'));
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
            fputcsv($out, MenuService::CSV_COLUMNS);
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
