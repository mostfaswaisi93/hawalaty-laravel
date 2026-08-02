<?php

namespace App\Http\Controllers\Web;

use App\Enums\BusinessCapability;
use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\ProductionRun;
use App\Services\Tenant\BusinessProfile;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\ProductionService;
use App\Services\Tenant\RecipeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ProductionController extends Controller
{
    use HasPerPage;

    public function __construct(
        private readonly ProductionService $service,
        private readonly RecipeService $recipes,
        private readonly BusinessProfile $business,
        private readonly CatalogNaming $naming,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المخزون.'));
        }

        $perPage = $this->perPage($request, 20);

        return view('production.index', [
            'runs' => $this->service->list($request->query('status'), $perPage),
            'producible' => MenuItem::query()
                ->stocked()
                ->has('recipeComponents')
                ->orderBy('name')
                ->get(),
            'locations' => Location::query()->active()->orderBy('name')->get(),
            'tracksBatches' => $this->business->has(BusinessCapability::Batches),
            'status' => $request->query('status'),
            'naming' => $this->naming,
            'perPage' => $perPage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $tracksBatches = $this->business->has(BusinessCapability::Batches);

        $data = $request->validate([
            'itemId' => ['required', 'string', 'exists:tenant.menu_items,id'],
            'locationId' => ['required', 'string', 'exists:tenant.locations,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'batchNumber' => [$tracksBatches ? 'required' : 'nullable', 'string', 'max:64'],
            'expiresAt' => [$tracksBatches ? 'required' : 'nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'batchNumber.required' => __('رقم دفعة الإنتاج مطلوب في هذا النشاط.'),
            'expiresAt.required' => __('تاريخ صلاحية الدفعة مطلوب في هذا النشاط.'),
        ]);

        try {
            $run = $this->service->create($data);
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('production.show', $run->id)
            ->with('success', __('تم إنشاء أمر الإنتاج كمسودة. راجع المكوّنات ثم رحّله.'));
    }

    public function show(string $production): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المخزون.'));
        }

        $run = ProductionRun::with(['item', 'location', 'teamMember'])->findOrFail($production);

        return view('production.show', [
            'run' => $run,
            'components' => $run->item instanceof MenuItem
                ? $this->recipes->explode($run->item, (float) $run->quantity)
                : collect(),
            'naming' => $this->naming,
        ]);
    }

    public function post(string $production): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        try {
            $this->service->post(ProductionRun::with('item')->findOrFail($production));
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('production.show', $production)
            ->with('success', __('تم ترحيل الإنتاج: خُصمت المكوّنات وأُضيفت الكمية المنتَجة.'));
    }

    public function cancel(string $production): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        try {
            $this->service->cancel(ProductionRun::findOrFail($production));
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('production.index')->with('success', __('تم إلغاء أمر الإنتاج.'));
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::InventoryManage);
    }
}
