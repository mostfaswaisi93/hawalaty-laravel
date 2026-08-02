<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Enums\WarrantyClaimStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\SerialNumber;
use App\Models\Tenant\WarrantyClaim;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\WarrantyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class WarrantyController extends Controller
{
    use HasPerPage;

    public function __construct(
        private readonly WarrantyService $warranty,
        private readonly CatalogNaming $naming,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المخزون.'));
        }

        $perPage = $this->perPage($request, 20);

        return view('warranty.index', [
            'claims' => $this->warranty->list($request->query('status'), $request->query('search'), $perPage),
            'summary' => $this->warranty->summary(),
            'offenders' => $this->warranty->worstOffenders(),
            'statuses' => WarrantyClaimStatus::cases(),
            'items' => MenuItem::query()->where('kind', 'goods')->orderBy('name')->get(),
            'naming' => $this->naming,
            'status' => $request->query('status'),
            'search' => $request->query('search'),
            'perPage' => $perPage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'serialId' => ['nullable', 'string', 'exists:tenant.serial_numbers,id'],
            'itemId' => ['required_without:serialId', 'nullable', 'string', 'exists:tenant.menu_items,id'],
            'reference' => ['nullable', 'string', 'max:64'],
            'customerName' => ['required', 'string', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:50'],
            'issue' => ['required', 'string', 'max:2000'],
            'underWarranty' => ['nullable', 'boolean'],
        ]);

        try {
            $claim = $this->warranty->open($data);
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warranty.show', $claim->id)->with('success', __('تم فتح مطالبة الضمان.'));
    }

    public function show(string $claim): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المخزون.'));
        }

        return view('warranty.show', [
            'claim' => WarrantyClaim::with(['item', 'serialNumber.item', 'transfer', 'teamMember'])->findOrFail($claim),
            'statuses' => WarrantyClaimStatus::cases(),
            'naming' => $this->naming,
        ]);
    }

    public function update(Request $request, string $claim): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'status' => ['required', Rule::enum(WarrantyClaimStatus::class)],
            'repairCost' => ['nullable', 'numeric', 'min:0'],
            'chargedAmount' => ['nullable', 'numeric', 'min:0'],
            'resolution' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->warranty->update(WarrantyClaim::findOrFail($claim), $data);

        return redirect()->route('warranty.show', $claim)->with('success', __('تم تحديث المطالبة.'));
    }

    /**
     * Look a unit up by its number while raising a claim, so the counter does
     * not have to leave the form to find out whether it is still covered.
     */
    public function lookup(Request $request): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المخزون.'));
        }

        $serial = (string) $request->query('serial', '');

        return view('warranty.lookup', [
            'serial' => $serial,
            'matches' => $serial === ''
                ? collect()
                : SerialNumber::query()
                    ->with(['item', 'customer', 'transfer'])
                    ->where('serial', 'like', '%'.trim($serial).'%')
                    ->orderByDesc('sold_at')
                    ->limit(25)
                    ->get(),
            'naming' => $this->naming,
        ]);
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::InventoryManage);
    }
}
