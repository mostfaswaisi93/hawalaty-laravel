<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Batch;
use App\Models\Tenant\Location;
use App\Services\Tenant\BatchService;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class BatchController extends Controller
{
    public function __construct(
        private readonly BatchService $batches,
        private readonly StockService $stock,
        private readonly CatalogNaming $naming,
    ) {}

    /**
     * Lots on hand, led by the two lists that need acting on today: what has
     * already expired, and what is about to.
     */
    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المخزون.'));
        }

        $locationId = filled($request->query('locationId')) ? (string) $request->query('locationId') : null;
        $soonDays = max(1, min(365, (int) $request->query('days', Batch::SOON_DAYS)));
        $search = $request->query('search');

        return view('batches.index', [
            'rows' => $this->batches->inStock($locationId, $search),
            'expired' => $this->batches->expiredOnHand($locationId),
            'expiringSoon' => $this->batches->expiringSoon($soonDays, $locationId),
            'expiredValue' => $this->batches->expiredValue($locationId),
            'locations' => Location::query()->active()->orderBy('name')->get(),
            'locationId' => $locationId,
            'soonDays' => $soonDays,
            'search' => $search,
            'naming' => $this->naming,
        ]);
    }

    /**
     * One lot: what is left, and who received the rest.
     *
     * This is the recall screen. Everything on it answers a question an
     * inspector or a supplier withdrawal notice will ask.
     */
    public function show(string $batch): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المخزون.'));
        }

        $model = Batch::with(['item', 'supplier'])->findOrFail($batch);

        return view('batches.show', [
            'batch' => $model,
            'onHand' => $model->onHand(),
            'movements' => $model->movements()->with(['location', 'teamMember'])->latest()->get(),
            'sales' => $this->batches->soldOn($model),
            'naming' => $this->naming,
        ]);
    }

    /**
     * Destroy expired or damaged stock from a specific lot.
     */
    public function writeOff(Request $request, string $batch): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
            'locationId' => ['nullable', 'string', 'exists:tenant.locations,id'],
        ], [
            'reason.required' => __('اذكر سبب الإتلاف — الإتلاف بدون سبب لا يُقبل.'),
        ]);

        try {
            $this->stock->writeOffBatch(
                Batch::with('item')->findOrFail($batch),
                (float) $data['quantity'],
                $data['reason'],
                $data['locationId'] ?? null,
            );
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('تم إتلاف الكمية من اللوط وتسجيلها في الدفتر.'));
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::InventoryManage);
    }
}
