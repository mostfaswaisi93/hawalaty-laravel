<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Http\Requests\Tenant\Supplier\StoreSupplierRequest;
use App\Http\Requests\Tenant\Supplier\UpdateSupplierRequest;
use App\Models\Tenant\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    use HasPerPage;

    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المشتريات.'));
        }

        $search = $request->query('search');
        $perPage = $this->perPage($request, 20);

        return view('suppliers.index', [
            'suppliers' => Supplier::query()
                ->withCount('purchases')
                ->whereSearch(['name', 'phone', 'email'], $search)
                ->orderBy('name')
                ->paginate($perPage)
                ->withQueryString(),
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        Supplier::create($this->attributes($request->validated()));

        return redirect()->route('suppliers.index')->with('success', __('تمت إضافة المورد.'));
    }

    public function update(UpdateSupplierRequest $request, string $supplier): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        Supplier::findOrFail($supplier)->update($this->attributes($request->validated()));

        return redirect()->route('suppliers.index')->with('success', __('تم تحديث المورد.'));
    }

    public function destroy(string $supplier): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        // Soft delete: purchases keep pointing at the supplier that raised them,
        // so the history of who supplied what stays intact.
        Supplier::findOrFail($supplier)->delete();

        return redirect()->route('suppliers.index')->with('success', __('تم حذف المورد.'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'tax_number' => $data['taxNumber'] ?? null,
            'payment_terms_days' => $data['paymentTermsDays'] ?? null,
            'notes' => $data['notes'] ?? null,
            'active' => (bool) ($data['active'] ?? true),
        ];
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::PurchasesManage);
    }
}
