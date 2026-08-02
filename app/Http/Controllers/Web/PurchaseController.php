<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Http\Requests\Tenant\Purchase\StorePurchaseRequest;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Purchase;
use App\Models\Tenant\Supplier;
use App\Services\Tenant\BusinessProfile;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PurchaseController extends Controller
{
    use HasPerPage;

    public function __construct(
        private readonly PurchaseService $service,
        private readonly BusinessProfile $business,
        private readonly CatalogNaming $naming,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المشتريات.'));
        }

        $perPage = $this->perPage($request, 20);

        return view('purchases.index', [
            'purchases' => $this->service->list($request->query('search'), $request->query('status'), $perPage),
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'perPage' => $perPage,
        ]);
    }

    public function create(): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المشتريات.'));
        }

        return view('purchases.create', $this->formData());
    }

    public function store(StorePurchaseRequest $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $purchase = $this->service->create($request->validated());

        return redirect()->route('purchases.show', $purchase->id)
            ->with('success', __('تم إنشاء أمر الشراء كمسودة. راجعه ثم استلمه لترحيل الكميات للمخزون.'));
    }

    public function show(string $purchase): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المشتريات.'));
        }

        return view('purchases.show', array_merge($this->formData(), [
            'purchase' => Purchase::with(['lines.item', 'supplier', 'location'])->findOrFail($purchase),
        ]));
    }

    public function update(StorePurchaseRequest $request, string $purchase): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        try {
            $this->service->update(Purchase::findOrFail($purchase), $request->validated());
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('purchases.show', $purchase)->with('success', __('تم تحديث أمر الشراء.'));
    }

    /**
     * The one-way door: stock and cost move here.
     */
    public function receive(string $purchase): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        try {
            $this->service->receive(Purchase::findOrFail($purchase));
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('purchases.show', $purchase)
            ->with('success', __('تم استلام البضاعة وترحيلها للمخزون وتحديث التكلفة.'));
    }

    public function cancel(string $purchase): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        try {
            $this->service->cancel(Purchase::findOrFail($purchase));
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('purchases.index')->with('success', __('تم إلغاء أمر الشراء.'));
    }

    public function destroy(string $purchase): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        try {
            $this->service->delete(Purchase::findOrFail($purchase));
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('purchases.index')->with('success', __('تم حذف أمر الشراء.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'suppliers' => Supplier::query()->active()->orderBy('name')->get(),
            'locations' => Location::query()->active()->orderBy('name')->get(),
            'items' => MenuItem::query()->stocked()->active()->orderBy('name')->get(),
            'business' => $this->business,
            'naming' => $this->naming,
        ];
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::PurchasesManage);
    }
}
