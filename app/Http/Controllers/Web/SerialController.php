<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Enums\SerialStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\SerialNumber;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\SerialService;
use App\Services\Tenant\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SerialController extends Controller
{
    use HasPerPage;

    public function __construct(
        private readonly SerialService $serials,
        private readonly StockService $stock,
        private readonly CatalogNaming $naming,
    ) {}

    /**
     * Search by number. The counter question is always "this one - what do you
     * know about it", so the screen leads with the box you type it into.
     */
    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المخزون.'));
        }

        $perPage = $this->perPage($request, 25);
        $search = $request->query('search');
        $status = $request->query('status');

        return view('serials.index', [
            'units' => SerialNumber::query()
                ->with(['item', 'customer'])
                ->whereSearch(['serial'], $search)
                ->when($status, fn ($query) => $query->where('status', $status))
                ->orderByDesc('received_at')
                ->paginate($perPage)
                ->withQueryString(),
            'statuses' => SerialStatus::cases(),
            'mismatches' => $this->serials->mismatches($this->stock),
            'expiringWarranty' => $this->serials->warrantyExpiringWithin(30),
            'naming' => $this->naming,
            'search' => $search,
            'status' => $status,
            'perPage' => $perPage,
        ]);
    }

    /**
     * One unit's whole life: where it came from, where it went, and whether the
     * promise made at the sale is still standing.
     */
    public function show(string $serial): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المخزون.'));
        }

        $unit = SerialNumber::with([
            'item', 'customer', 'transfer', 'purchase.supplier', 'batch', 'location',
            'claims' => fn ($query) => $query->latest('reported_at'),
            'movements' => fn ($query) => $query->with(['location', 'teamMember'])->latest(),
        ])->findOrFail($serial);

        return view('serials.show', [
            'unit' => $unit,
            'naming' => $this->naming,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'itemId' => ['required', 'string', 'exists:tenant.menu_items,id'],
            'serials' => ['required', 'string', 'max:20000'],
            'unitCost' => ['nullable', 'numeric', 'min:0'],
            'locationId' => ['nullable', 'string', 'exists:tenant.locations,id'],
        ]);

        $item = MenuItem::findOrFail($data['itemId']);

        try {
            $this->serials->register(
                $item,
                preg_split('/[\r\n,]+/', $data['serials']) ?: [],
                (float) ($data['unitCost'] ?? $item->cost),
                $data['locationId'] ?? null,
            );
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('serials.index')->with('success', __('تم تسجيل الأرقام التسلسلية.'));
    }

    public function update(Request $request, string $serial): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'action' => ['required', Rule::in(['restock', 'scrap'])],
            'reason' => ['required_if:action,scrap', 'nullable', 'string', 'max:255'],
        ], [
            'reason.required_if' => __('اذكر سبب الإتلاف.'),
        ]);

        $unit = SerialNumber::with('item')->findOrFail($serial);

        try {
            if ($data['action'] === 'scrap') {
                // Both halves in one act: the unit stops being sellable and the
                // quantity leaves the ledger.
                $this->stock->scrapSerial($unit, (string) $data['reason']);
                $this->serials->scrap($unit, (string) $data['reason']);
            } else {
                $this->serials->restock($unit);
            }
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('serials.show', $serial)->with('success', __('تم تحديث حالة الوحدة.'));
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::InventoryManage);
    }
}
