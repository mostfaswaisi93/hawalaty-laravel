<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Http\Controllers\Web\Concerns\ListsAccounts;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\WorkOrder;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\SchedulingService;
use App\Services\Tenant\WorkOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class WorkOrderController extends Controller
{
    use HasPerPage, ListsAccounts;

    public function __construct(
        private readonly WorkOrderService $service,
        private readonly SchedulingService $scheduling,
        private readonly CatalogNaming $naming,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة أوامر الشغل.'));
        }

        $perPage = $this->perPage($request, 20);

        return view('work-orders.index', [
            'orders' => $this->service->list($request->query('status'), $request->query('search'), $perPage),
            'summary' => $this->service->summary(),
            'statuses' => WorkOrderStatus::cases(),
            'resources' => $this->scheduling->resources(),
            'status' => $request->query('status'),
            'search' => $request->query('search'),
            'perPage' => $perPage,
            'naming' => $this->naming,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'customerName' => ['required', 'string', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:64'],
            'resourceId' => ['nullable', 'string', 'exists:tenant.service_resources,id'],
            'subject' => ['nullable', 'string', 'max:255'],
            'registration' => ['nullable', 'string', 'max:32'],
            'odometer' => ['nullable', 'integer', 'min:0'],
            'complaint' => ['nullable', 'string', 'max:2000'],
            'workmanshipWarrantyDays' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ]);

        $order = $this->service->create($data);

        return redirect()->route('work-orders.show', $order->id)->with('success', __('تم فتح أمر الشغل.'));
    }

    public function show(string $workOrder): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة أوامر الشغل.'));
        }

        return view('work-orders.show', [
            'order' => WorkOrder::with(['lines.item', 'resource', 'transfer', 'teamMember'])->findOrFail($workOrder),
            'statuses' => WorkOrderStatus::cases(),
            'resources' => $this->scheduling->resources(),
            'items' => MenuItem::query()->sellable()->orderBy('kind')->orderBy('name')->get(),
            'accounts' => $this->activeAccounts(),
            'naming' => $this->naming,
        ]);
    }

    public function update(Request $request, string $workOrder): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'status' => ['nullable', Rule::enum(WorkOrderStatus::class)],
            'resourceId' => ['nullable', 'string', 'exists:tenant.service_resources,id'],
            'subject' => ['nullable', 'string', 'max:255'],
            'registration' => ['nullable', 'string', 'max:32'],
            'odometer' => ['nullable', 'integer', 'min:0'],
            'complaint' => ['nullable', 'string', 'max:2000'],
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            'workmanshipWarrantyDays' => ['nullable', 'integer', 'min:0', 'max:3650'],

            'lines' => ['nullable', 'array'],
            'lines.*.itemId' => ['required', 'string', 'exists:tenant.menu_items,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unitPrice' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->service->update(WorkOrder::findOrFail($workOrder), $data);
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('work-orders.show', $workOrder)->with('success', __('تم حفظ أمر الشغل.'));
    }

    /**
     * Hand the job to the till. Everything else - stock, ledger, receipt -
     * follows from the ordinary sale path.
     */
    public function invoice(Request $request, string $workOrder): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'paymentMethod' => ['required', Rule::in(['cash', 'bank'])],
            'accountId' => ['nullable', 'required_if:paymentMethod,bank', 'string', 'exists:tenant.accounts,id'],
            'transferredAmount' => ['nullable', 'required_if:paymentMethod,bank', 'numeric', 'min:0'],
            'referenceNumber' => ['nullable', 'required_if:paymentMethod,bank', 'string', 'max:100'],
        ]);

        try {
            $order = $this->service->invoice(WorkOrder::findOrFail($workOrder), array_filter($data, fn ($v) => $v !== null));
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('transfers.created', $order->transfer_id)
            ->with('success', __('تم تسليم أمر الشغل وإصدار الفاتورة.'));
    }

    public function cancel(string $workOrder): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        try {
            $this->service->cancel(WorkOrder::findOrFail($workOrder));
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('work-orders.index')->with('success', __('تم إلغاء أمر الشغل.'));
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::TransfersCreate);
    }
}
