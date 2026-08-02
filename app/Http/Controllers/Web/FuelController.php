<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Models\Tenant\FuelPrice;
use App\Models\Tenant\FuelShift;
use App\Models\Tenant\FuelTank;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuItem;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\FuelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class FuelController extends Controller
{
    use HasPerPage;

    public function __construct(
        private readonly FuelService $fuel,
        private readonly CatalogNaming $naming,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المحروقات.'));
        }

        $perPage = $this->perPage($request, 20);
        $open = FuelShift::query()->open()->with(['readings.pump.tank', 'dips.tank'])->first();

        return view('fuel.index', [
            'openShift' => $open,
            'reconciliation' => $open ? $this->fuel->reconcile($open) : collect(),
            'shifts' => FuelShift::query()->with('teamMember')->latest('shift_date')->paginate($perPage)->withQueryString(),
            'tanks' => FuelTank::query()->with(['item', 'pumps'])->orderBy('name')->get(),
            'service' => $this->fuel,
            'perPage' => $perPage,
            'naming' => $this->naming,
        ]);
    }

    public function openShift(Request $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'shiftDate' => ['nullable', 'date'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->fuel->openShift($data);
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('fuel.index')->with('success', __('فُتحت الوردية بقراءات الإقفال السابقة.'));
    }

    public function record(Request $request, string $shift): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'readings' => ['array'],
            'readings.*.closing' => ['nullable', 'numeric', 'min:0'],
            'readings.*.test' => ['nullable', 'numeric', 'min:0'],
            'readings.*.price' => ['nullable', 'numeric', 'min:0'],
            'dips' => ['array'],
            'dips.*.closing' => ['nullable', 'numeric', 'min:0'],
            'dips.*.deliveries' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->fuel->record(
                FuelShift::with(['readings', 'dips'])->findOrFail($shift),
                $data['readings'] ?? [],
                $data['dips'] ?? [],
            );
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('fuel.index')->with('success', __('تم حفظ القراءات.'));
    }

    public function closeShift(string $shift): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        try {
            $this->fuel->closeShift(FuelShift::findOrFail($shift));
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('fuel.shift', $shift)->with('success', __('أُقفلت الوردية ورُحّلت المبيعات.'));
    }

    public function shift(string $shift): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المحروقات.'));
        }

        $model = FuelShift::with(['readings.pump.tank.item', 'dips.tank.item', 'teamMember'])->findOrFail($shift);

        return view('fuel.shift', [
            'shift' => $model,
            'reconciliation' => $this->fuel->reconcile($model),
            'service' => $this->fuel,
            'naming' => $this->naming,
        ]);
    }

    /**
     * Tanks, pumps and the price board.
     */
    public function equipment(): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المحروقات.'));
        }

        $grades = MenuItem::query()->where('kind', 'goods')->where('unit_code', 'LTR')->orderBy('name')->get();

        return view('fuel.equipment', [
            'tanks' => FuelTank::query()->with(['item', 'pumps'])->orderBy('name')->get(),
            'grades' => $grades,
            'locations' => Location::query()->active()->orderBy('name')->get(),
            'prices' => FuelPrice::query()->with('item')->latest('effective_from')->limit(20)->get(),
            'service' => $this->fuel,
            'naming' => $this->naming,
        ]);
    }

    public function storeTank(Request $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('tenant.fuel_tanks', 'name')],
            'itemId' => ['required', 'string', 'exists:tenant.menu_items,id'],
            'locationId' => ['nullable', 'string', 'exists:tenant.locations,id'],
            'capacity' => ['nullable', 'numeric', 'min:0'],
            'minimumLevel' => ['nullable', 'numeric', 'min:0'],
        ]);

        FuelTank::create([
            'name' => $data['name'],
            'menu_item_id' => $data['itemId'],
            'location_id' => $data['locationId'] ?? Location::default()?->id,
            'capacity' => $data['capacity'] ?? 0,
            'minimum_level' => $data['minimumLevel'] ?? 0,
            'active' => true,
        ]);

        return redirect()->route('fuel.equipment')->with('success', __('تمت إضافة الخزان.'));
    }

    public function storePump(Request $request, string $tank): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('tenant.fuel_pumps', 'name')],
            'sealNumber' => ['nullable', 'string', 'max:64'],
            'calibratedAt' => ['nullable', 'date'],
        ]);

        FuelTank::findOrFail($tank)->pumps()->create([
            'name' => $data['name'],
            'seal_number' => $data['sealNumber'] ?? null,
            'calibrated_at' => $data['calibratedAt'] ?? null,
            'active' => true,
        ]);

        return redirect()->route('fuel.equipment')->with('success', __('تمت إضافة المضخة.'));
    }

    public function storePrice(Request $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'itemId' => ['required', 'string', 'exists:tenant.menu_items,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'effectiveFrom' => ['required', 'date'],
        ]);

        $this->fuel->setPrice(MenuItem::findOrFail($data['itemId']), (float) $data['price'], $data['effectiveFrom']);

        return redirect()->route('fuel.equipment')->with('success', __('تم تسجيل السعر الجديد.'));
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::InventoryManage);
    }
}
