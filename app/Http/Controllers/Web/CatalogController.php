<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Catalog\CatalogItemRequest;
use App\Models\Tenant\Currency;
use App\Services\Tenant\CatalogRegistry;
use App\Services\Tenant\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The reference catalogs screen - one tabbed page managing the six lists the
 * accounting redesign is built on (currencies, bank names, wallet names,
 * account types, wallet types, cash boxes). See App\Services\Tenant\CatalogRegistry.
 *
 * Gated on BanksManage: these lists are the banks/wallets vocabulary, so the
 * permission that governs payment methods governs them too - no new permission
 * key to seed into every tenant database.
 *
 * String ids + manual find (not route-model binding), like BankController, so
 * every tenant-table lookup runs after ResolveTenantConnection has pointed the
 * `tenant` connection at this license's database.
 */
class CatalogController extends Controller
{
    public function __construct(
        private readonly CatalogRegistry $registry,
        private readonly CatalogService $service,
    ) {}

    public function index(): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة الكتالوجات.'));
        }

        $catalogs = [];

        foreach ($this->registry->all() as $key => $meta) {
            /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
            $model = $meta['model'];

            $query = $model::query()->orderBy('name');

            // Banks / wallets / cash boxes carry a supported-currencies pivot;
            // eager-load it so the page can show and preselect them.
            if ($meta['currencies']) {
                $query->with('currencies');
            }

            $catalogs[$key] = [
                ...$meta,
                'items' => $query->get(),
            ];
        }

        return view('catalogs.index', [
            'catalogs' => $catalogs,
            // The pool the supported-currencies multi-select is drawn from.
            'currencyOptions' => Currency::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(CatalogItemRequest $request, string $catalog): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->create($this->registry->modelFor($catalog), $request->validated());

        return $this->back($catalog, __('تمت الإضافة بنجاح.'));
    }

    public function update(CatalogItemRequest $request, string $catalog, string $id): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $model = $this->registry->modelFor($catalog);
        $this->service->update($model::findOrFail($id), $request->validated());

        return $this->back($catalog, __('تم التحديث بنجاح.'));
    }

    public function destroy(string $catalog, string $id): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $meta = $this->registry->get($catalog);
        $model = $meta['model'];

        try {
            $this->service->delete($model::findOrFail($id), $meta['fk']);
        } catch (ValidationException $e) {
            return redirect()
                ->route('catalogs.index')
                ->withFragment($catalog)
                ->with('error', $e->validator->errors()->first());
        }

        return $this->back($catalog, __('تم الحذف.'));
    }

    /**
     * Redirect back to the page with the acted-on catalog's tab reselected -
     * the fragment (#currencies, #banks, ...) is read by the page on load.
     */
    private function back(string $catalog, string $message): RedirectResponse
    {
        abort_unless($this->registry->has($catalog), 404);

        return redirect()
            ->route('catalogs.index')
            ->withFragment($catalog)
            ->with('success', $message);
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::BanksManage);
    }
}
