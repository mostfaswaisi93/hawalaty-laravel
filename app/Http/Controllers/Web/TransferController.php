<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Transfer\StoreTransferRequest;
use App\Models\Tenant\Account;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Transfer;
use App\Models\Tenant\MenuCategory;
use App\Services\Tenant\TransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Js;
use Illuminate\View\View;

/**
 * Server-rendered "New Order" builder - same TransferService and
 * StoreTransferRequest as Api\V1\TransferController::store(), just fed by
 * a normal POST from resources/views/transfers/index.blade.php's Alpine
 * cart instead of a JSON body. See that view for the array-input naming
 * convention (items[N][itemId] etc.) the Alpine state serializes into
 * before submit.
 */
class TransferController extends Controller
{
    public function __construct(private readonly TransferService $service) {}

    public function index(): View|RedirectResponse
    {
        $member = auth('tenant')->user();

        if (! $member?->hasPermission(PermissionKey::TransfersCreate)) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإنشاء طلبات جديدة.'));
        }

        $categories = MenuCategory::query()
            ->with(['menuItems' => fn ($query) => $query->where('active', true)->orderBy('name')])
            ->orderBy('position')
            ->get()
            ->filter(fn (MenuCategory $category) => $category->menuItems->isNotEmpty())
            ->values();

        // Phase 2d: the order form now picks an ACCOUNT, not a legacy bank. Both
        // kinds come down in one list with the fields the picker shows (holder,
        // institution, type, currency); the form filters by `kind` client-side.
        $accounts = Account::query()
            ->where('active', true)
            ->with(['currency', 'bankName', 'walletName', 'accountType', 'walletType', 'cashBox'])
            ->orderBy('holder_name')
            ->get()
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'kind' => $account->kind->value,
                'holder' => $account->holder_name,
                'institution' => $account->institutionName(),
                'type' => $account->typeName(),
                'currency' => $account->currency?->symbol,
                'iban' => $account->iban,
                'walletPhone' => $account->wallet_phone,
            ])
            ->values();

        $customerNames = Customer::query()->orderBy('name')->pluck('name');

        // Handed to Alpine as a JSON blob (x-data="orderBuilder(...)") so the
        // menu browser/search/category filter runs entirely client-side
        // without extra requests - see BACKEND_PROMPT-style brief for this
        // task.
        $menuJson = Js::from(
            $categories->map(fn (MenuCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'items' => $category->menuItems->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => (float) $item->price,
                ])->values(),
            ])->values()
        );

        return view('transfers.index', [
            'categories' => $categories,
            'accounts' => $accounts,
            'menuJson' => $menuJson,
            'customerNames' => $customerNames,
        ]);
    }

    public function store(StoreTransferRequest $request): RedirectResponse
    {
        $member = auth('tenant')->user();

        if (! $member?->hasPermission(PermissionKey::TransfersCreate)) {
            abort(403);
        }

        $transfer = $this->service->create($request->validated());

        // Straight to the confirmation, not back to an empty form: the operator's
        // next move is always either "print it" or "take the next order", and
        // both are one tap from there.
        return redirect()->route('transfers.created', $transfer->id);
    }

    /**
     * The just-created order, with its two follow-up actions. A page rather
     * than a flash message because the receipt has to be reachable AFTER the
     * order is made - a toast that scrolls away takes the print link with it.
     */
    public function created(string $transfer): View|RedirectResponse
    {
        $member = auth('tenant')->user();

        if (! $member?->hasPermission(PermissionKey::TransfersCreate)) {
            return redirect()->route('dashboard');
        }

        return view('transfers.created', [
            'order' => Transfer::with(['lineItems', 'account.currency'])->findOrFail($transfer),
        ]);
    }
}
