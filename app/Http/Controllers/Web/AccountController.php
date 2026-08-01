<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Http\Requests\Tenant\Account\StoreAccountRequest;
use App\Http\Requests\Tenant\Account\StoreLedgerEntryRequest;
use App\Http\Requests\Tenant\Account\UpdateAccountRequest;
use App\Models\Tenant\Account;
use App\Models\Tenant\AccountType;
use App\Models\Tenant\BankName;
use App\Models\Tenant\CashBox;
use App\Models\Tenant\Currency;
use App\Models\Tenant\WalletName;
use App\Models\Tenant\WalletType;
use App\Services\Tenant\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    use HasPerPage;

    public function __construct(private readonly AccountService $service) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->denyUnlessManage()) {
            return $redirect;
        }

        $search = $request->query('search');
        $perPage = $this->perPage($request, 20);

        $accounts = Account::query()
            ->with(['currency', 'bankName', 'walletName', 'accountType', 'walletType', 'cashBox'])
            ->whereSearch('holder_name', $search)
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('accounts.index', [
            'accounts' => $accounts,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function create(): View|RedirectResponse
    {
        if ($redirect = $this->denyUnlessManage()) {
            return $redirect;
        }

        return view('accounts.create', $this->formData());
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $account = $this->service->create($request->validated());

        return redirect()->route('accounts.show', $account->id)->with('success', __('تم إنشاء الحساب بنجاح.'));
    }

    public function show(Request $request, string $account): View|RedirectResponse
    {
        if ($redirect = $this->denyUnlessManage()) {
            return $redirect;
        }

        $account = Account::with(['currency', 'bankName', 'walletName', 'accountType', 'walletType', 'cashBox'])->findOrFail($account);
        $perPage = $this->perPage($request, 20);

        $entries = $account->ledgerEntries()
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('accounts.show', [
            'account' => $account,
            'entries' => $entries,
            'perPage' => $perPage,
        ]);
    }

    public function edit(string $account): View|RedirectResponse
    {
        if ($redirect = $this->denyUnlessManage()) {
            return $redirect;
        }

        $account = Account::findOrFail($account);

        return view('accounts.edit', [
            'account' => $account,
            ...$this->formData(),
        ]);
    }

    public function update(UpdateAccountRequest $request, string $account): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->update(Account::findOrFail($account), $request->validated());

        return redirect()->route('accounts.show', $account)->with('success', __('تم تحديث الحساب بنجاح.'));
    }

    public function destroy(string $account): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        try {
            $this->service->delete(Account::findOrFail($account));
        } catch (ValidationException $e) {
            return redirect()->route('accounts.index')->with('error', $e->validator->errors()->first());
        }

        return redirect()->route('accounts.index')->with('success', __('تم حذف الحساب.'));
    }

    public function storeEntry(StoreLedgerEntryRequest $request, string $account): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $account = Account::findOrFail($account);
        $data = $request->validated();
        $amount = (float) $data['amount'];

        $this->service->postEntry(
            $account,
            $data['direction'] === 'debit' ? $amount : 0.0,
            $data['direction'] === 'credit' ? $amount : 0.0,
            $data['description'] ?? null,
        );

        return redirect()->route('accounts.show', $account->id)->with('success', __('تمت إضافة الحركة.'));
    }

    private function formData(): array
    {
        return [
            'banks' => BankName::query()->where('active', true)->with('currencies:id')->orderBy('name')->get(),
            'wallets' => WalletName::query()->where('active', true)->with('currencies:id')->orderBy('name')->get(),
            'accountTypes' => AccountType::query()->where('active', true)->orderBy('name')->get(),
            'walletTypes' => WalletType::query()->where('active', true)->orderBy('name')->get(),
            'cashBoxes' => CashBox::query()->where('active', true)->with('currencies:id')->orderBy('name')->get(),
            'currencies' => Currency::query()->where('active', true)->orderBy('name')->get(),
        ];
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::BanksManage);
    }

    private function denyUnlessManage(): ?RedirectResponse
    {
        return $this->canManage()
            ? null
            : redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة الحسابات.'));
    }
}
