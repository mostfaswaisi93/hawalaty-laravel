<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Http\Controllers\Web\Concerns\ListsAccounts;
use App\Http\Requests\Tenant\SaleReturn\StoreSaleReturnRequest;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\SaleReturn;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\InvoiceService;
use App\Services\Tenant\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ReturnController extends Controller
{
    use HasPerPage, ListsAccounts;

    public function __construct(
        private readonly ReturnService $returns,
        private readonly CatalogNaming $naming,
        private readonly InvoiceService $invoices,
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

        $record = SaleReturn::with(['lines.item', 'transfer', 'teamMember', 'account'])->findOrFail($saleReturn);

        return view('returns.show', [
            'return' => $record,
            'accounts' => $this->activeAccounts(),
            'allowsRestock' => $this->returns->allowsRestock(),
            'naming' => $this->naming,

            // A refund the customer holds a tax invoice for needs a credit note
            // against it, or the tax already declared is never reclaimed.
            'creditNote' => Invoice::where('sale_return_id', $record->id)->first(),
            'invoicingConfigured' => $this->invoices->isConfigured(),
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
        // One source for the rules, shared with the offline sync channel. Two
        // differ on edit: the original sale is fixed, and the customer name is
        // already on the record.
        $rules = StoreSaleReturnRequest::baseRules();

        if (! $creating) {
            $rules['transferId'] = ['prohibited'];
            $rules['customerName'] = ['nullable', 'string', 'max:255'];
        }

        return $request->validate($rules, [
            'lines.required' => __('اختر بندًا واحدًا على الأقل للإرجاع.'),
            'accountId.required_if' => __('حدّد الحساب الذي سيُرد منه المبلغ.'),
        ]);
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::TransfersCreate);
    }
}
