<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Bank\StoreBankRequest;
use App\Http\Requests\Tenant\Bank\UpdateBankRequest;
use App\Models\Tenant\Bank;
use App\Services\Tenant\BankService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Server-rendered banks & wallets management. Gated on BanksManage. Uses
 * string ids + manual find (not route-model binding) so every tenant-table
 * lookup happens inside the controller — after ResolveTenantConnection has
 * pointed the `tenant` connection at this license's database.
 */
class BankController extends Controller
{
    public function __construct(private readonly BankService $service) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->denyUnlessManage()) {
            return $redirect;
        }

        $search = $request->query('search');

        return view('banks.index', [
            'banks' => $this->service->list($search),
            'search' => $search,
        ]);
    }

    public function store(StoreBankRequest $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->create($request->validated());

        return redirect()->route('banks.index')->with('success', __('تم إضافة البنك بنجاح.'));
    }

    public function update(UpdateBankRequest $request, string $bank): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->update(Bank::findOrFail($bank), $request->validated());

        return redirect()->route('banks.index')->with('success', __('تم تحديث البنك بنجاح.'));
    }

    public function destroy(string $bank): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->delete(Bank::findOrFail($bank));

        return redirect()->route('banks.index')->with('success', __('تم حذف البنك.'));
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::BanksManage);
    }

    private function denyUnlessManage(): ?RedirectResponse
    {
        return $this->canManage()
            ? null
            : redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة البنوك.'));
    }
}
