<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuItem;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\VariantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class VariantController extends Controller
{
    use HasPerPage;

    public function __construct(
        private readonly VariantService $variants,
        private readonly CatalogNaming $naming,
    ) {}

    /**
     * Styles, with how many variants each carries and how much stock in total.
     */
    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة القائمة.'));
        }

        $perPage = $this->perPage($request, 20);
        $search = $request->query('search');

        $parents = MenuItem::query()
            ->whereNull('parent_item_id')
            ->has('variants')
            ->with(['menuCategory', 'variants', 'variantAxes'])
            ->whereSearch(['name', 'sku'], $search)
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('variants.index', [
            'parents' => $parents,
            'stock' => $this->variants->stockByParent($parents->getCollection()),
            'candidates' => MenuItem::query()
                ->whereNull('parent_item_id')
                ->doesntHave('variants')
                ->where('kind', 'goods')
                ->orderBy('name')
                ->get(),
            'naming' => $this->naming,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    /**
     * The matrix for one style: generate combinations, then read the grid.
     */
    public function edit(Request $request, string $item): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة القائمة.'));
        }

        $parent = MenuItem::with(['variants.variantValues', 'variantAxes'])->findOrFail($item);
        $locationId = filled($request->query('locationId')) ? (string) $request->query('locationId') : null;

        return view('variants.edit', [
            'parent' => $parent,
            'axes' => $this->variants->availableAxes(),
            'grid' => $this->variants->grid($parent, $locationId),
            'locations' => Location::query()->active()->orderBy('name')->get(),
            'locationId' => $locationId,
            'service' => $this->variants,
            'naming' => $this->naming,
        ]);
    }

    public function generate(Request $request, string $item): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'axes' => ['required', 'array', 'min:1'],
            'axes.*' => ['array'],
            'axes.*.*' => ['string', 'exists:tenant.constant_list_items,id'],
        ], [
            'axes.required' => __('اختر محورًا واحدًا على الأقل.'),
        ]);

        try {
            $result = $this->variants->generate(MenuItem::findOrFail($item), $data['axes']);
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('variants.edit', $item)->with(
            'success',
            $result['created'] > 0
                ? __('أُنشئت :created تنويعة جديدة (:existing موجودة مسبقًا).', $result)
                : __('كل التنويعات المطلوبة موجودة مسبقًا.'),
        );
    }

    /**
     * Per-variant identifiers and price. Barcodes must differ - GS1 gives each
     * variant its own GTIN - so this is where they get set.
     */
    public function update(Request $request, string $item): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $parent = MenuItem::with('variants')->findOrFail($item);

        $data = $request->validate([
            'variants' => ['array'],
            'variants.*.sku' => ['nullable', 'string', 'max:64'],
            'variants.*.barcode' => ['nullable', 'string', 'max:64'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.active' => ['nullable', 'boolean'],
        ]);

        try {
            foreach ($parent->variants as $variant) {
                $row = $data['variants'][$variant->id] ?? null;

                if ($row === null) {
                    continue;
                }

                $variant->update([
                    'sku' => filled($row['sku'] ?? null) ? $row['sku'] : null,
                    'barcode' => filled($row['barcode'] ?? null) ? $row['barcode'] : null,
                    'price' => $row['price'] ?? $variant->price,
                    'active' => (bool) ($row['active'] ?? false),
                ]);
            }
        } catch (Throwable $e) {
            // Duplicate SKU or barcode is the realistic failure here, and the
            // database is the thing that knows.
            return redirect()->back()->with('error', __('تعذّر الحفظ: تأكّد أن الرمز والباركود غير مكرّرين.'));
        }

        return redirect()->route('variants.edit', $item)->with('success', __('تم حفظ التنويعات.'));
    }

    public function destroy(string $item, string $variant): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        try {
            $this->variants->remove(MenuItem::findOrFail($variant));
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('variants.edit', $item)->with('success', __('تم حذف التنويعة.'));
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::MenuManage);
    }
}
