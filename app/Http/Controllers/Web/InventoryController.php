<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Http\Requests\Tenant\Inventory\StoreMovementRequest;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\StockMovement;
use App\Services\Tenant\BusinessProfile;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class InventoryController extends Controller
{
    use HasPerPage;

    public function __construct(
        private readonly StockService $stock,
        private readonly BusinessProfile $business,
        private readonly CatalogNaming $naming,
    ) {}

    /**
     * On-hand across the catalog, with the two exception lists that actually get
     * acted on: what needs reordering and what has gone negative.
     */
    public function index(Request $request): View
    {
        $locationId = $this->resolveLocationFilter($request);
        $perPage = $this->perPage($request, 25);
        $search = $request->query('search');

        $items = MenuItem::query()
            ->stocked()
            ->with('menuCategory')
            ->whereSearch(['name', 'sku', 'barcode'], $search)
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $onHand = $this->stock->onHandFor($items->pluck('id')->all(), $locationId);

        return view('inventory.index', [
            'items' => $items,
            'onHand' => $onHand,
            'locations' => Location::query()->active()->orderBy('name')->get(),
            'locationId' => $locationId,
            'stockValue' => $this->stock->stockValue($locationId),
            'lowStock' => $this->stock->belowReorderPoint($locationId),
            'negatives' => $this->stock->negativeBalances($locationId),
            'naming' => $this->naming,
            'business' => $this->business,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    /**
     * The ledger for one item: every movement that produced its balance.
     */
    public function show(Request $request, string $item): View
    {
        $model = MenuItem::findOrFail($item);
        $locationId = $this->resolveLocationFilter($request);
        $perPage = $this->perPage($request, 30);

        return view('inventory.show', [
            'item' => $model,
            'movements' => StockMovement::query()
                ->forItem($model->id)
                ->atLocation($locationId)
                ->with(['location', 'teamMember'])
                ->latest()
                ->paginate($perPage)
                ->withQueryString(),
            'onHand' => $this->stock->onHand($model->id, $locationId),
            'locations' => Location::query()->active()->orderBy('name')->get(),
            'locationId' => $locationId,
            'naming' => $this->naming,
            'business' => $this->business,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Manual movements: an adjustment to a counted figure, a write-off, or a
     * move between locations. Everything else is posted by the flow that caused
     * it, not typed in here.
     */
    public function store(StoreMovementRequest $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validated();
        $item = MenuItem::findOrFail($data['itemId']);

        try {
            match ($data['type']) {
                StockMovementType::Adjustment->value => $this->stock->adjust(
                    $item,
                    (float) $data['quantity'],
                    $data['reason'],
                    $data['locationId'] ?? null,
                ),
                StockMovementType::Waste->value => $this->stock->waste(
                    $item,
                    (float) $data['quantity'],
                    $data['reason'],
                    $data['locationId'] ?? null,
                ),
                StockMovementType::Transfer->value => $this->stock->transfer(
                    $item,
                    (float) $data['quantity'],
                    $data['locationId'],
                    $data['toLocationId'],
                ),
            };
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('تم تسجيل الحركة المخزنية.'));
    }

    private function resolveLocationFilter(Request $request): ?string
    {
        $locationId = $request->query('locationId');

        return filled($locationId) ? (string) $locationId : null;
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::InventoryManage);
    }
}
