<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Http\Controllers\Web\Concerns\ListsAccounts;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\SaleReturn;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ReturnController extends Controller
{
    use HasPerPage, ListsAccounts;

    public function __construct(
        private readonly ReturnService $returns,
        private readonly CatalogNaming $naming,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المرتجعات.'));
        }

        $perPage = $this->perPage($request, 20);

        return view('returns.index', [
            'returns' => $this->returns->list($request->query('status'), $request->query('search'), $perPage),
            'summary' => $this->returns->summary(),
            'allowsRestock' => $this->returns->allowsRestock(),
            'windowDays' => $this->returns->windowDays(),
            'status' => $request->query('status'),
            'search' => $request->query('search'),
            'perPage' => $perPage,
            'naming' => $this->naming,
        ]);
    }

    /**
     * Start from the original sale wherever there is one - it is the only way
     * the quantities and prices can be trusted.
     */
    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المرتجعات.'));
        }

        $search = (string) $request->query('search', '');
        $transferId = $request->query('transferId');

        $transfer = filled($transferId)
            ? Transfer::with('lineItems.menuItem')->find($transferId)
            : null;

        return view('returns.create', [
            'search' => $search,
            'matches' => $search === '' ? collect() : Transfer::query()
                ->whereSearch(['customer', 'reference_number'], $search)
                ->latest()
                ->limit(25)
                ->get(),
            'transfer' => $transfer,
            'lines' => $transfer ? $this->returns->returnableLines($transfer) : collect(),
            'window' => $this->returns->windowFor($transfer),
            'allowsRestock' => $this->returns->allowsRestock(),
            'windowDays' => $this->returns->windowDays(),
            'accounts' => $this->activeAccounts(),
            'items' => MenuItem::query()->sellable()->orderBy('name')->get(),
            'naming' => $this->naming,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $this->validated($request, creating: true);

        try {
            $return = $this->returns->create($data);
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('returns.show', $return->id)
            ->with('success', __('أُنشئ المرتجع كمسودة. راجعه ثم نفّذه.'));
    }

    public function show(string $saleReturn): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المرتجعات.'));
        }

        return view('returns.show', [
            'return' => SaleReturn::with(['lines.item', 'transfer', 'teamMember', 'account'])->findOrFail($saleReturn),
            'accounts' => $this->activeAccounts(),
            'allowsRestock' => $this->returns->allowsRestock(),
            'naming' => $this->naming,
        ]);
    }

    public function update(Request $request, string $saleReturn): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $this->validated($request, creating: false);

        try {
            $this->returns->update(SaleReturn::with('transfer')->findOrFail($saleReturn), $data);
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('returns.show', $saleReturn)->with('success', __('تم حفظ المرتجع.'));
    }

    public function complete(string $saleReturn): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        try {
            $this->returns->complete(SaleReturn::findOrFail($saleReturn));
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('returns.show', $saleReturn)
            ->with('success', __('نُفِّذ المرتجع: أُعيد المخزون ورُدّ المبلغ.'));
    }

    public function reject(Request $request, string $saleReturn): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        try {
            $this->returns->reject(SaleReturn::findOrFail($saleReturn), $data['reason'] ?? null);
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('returns.index')->with('success', __('تم رفض المرتجع.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'transferId' => [$creating ? 'nullable' : 'prohibited', 'string', 'exists:tenant.transfers,id'],
            'customerName' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'refundMethod' => ['required', Rule::in(['cash', 'bank', 'store_credit'])],
            'accountId' => ['nullable', 'required_if:refundMethod,bank', 'string', 'exists:tenant.accounts,id'],
            'restockingFee' => ['nullable', 'numeric', 'min:0'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.transferLineItemId' => ['nullable', 'string', 'exists:tenant.transfer_line_items,id'],
            'lines.*.itemId' => ['nullable', 'required_without:lines.*.transferLineItemId', 'string', 'exists:tenant.menu_items,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unitPrice' => ['nullable', 'numeric', 'min:0'],
            'lines.*.condition' => ['nullable', Rule::in(['resalable', 'damaged'])],
            'lines.*.serialId' => ['nullable', 'string', 'exists:tenant.serial_numbers,id'],
            'lines.*.batchId' => ['nullable', 'string', 'exists:tenant.batches,id'],
        ], [
            'lines.required' => __('اختر بندًا واحدًا على الأقل للإرجاع.'),
            'accountId.required_if' => __('حدّد الحساب الذي سيُرد منه المبلغ.'),
        ]);
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::TransfersCreate);
    }
}
