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
            $model = $meta['model'];

            $query = $model::query()->orderBy('name');

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
