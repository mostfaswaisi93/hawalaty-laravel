<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Transfer\StoreTransferRequest;
use App\Models\Tenant\Account;
use App\Models\Tenant\ConstantList;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Transfer;
use App\Models\Tenant\MenuCategory;
use App\Services\Tenant\TransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Js;
use Illuminate\View\View;
use Throwable;

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
            ->with(['menuItems' => fn ($query) => $query->where('active', true)->orderBy('name')
                ->with(['modifierGroups' => fn ($groups) => $groups->where('active', true)
                    ->with(['modifiers' => fn ($modifiers) => $modifiers->where('active', true)])])])
            ->orderBy('position')
            ->get()
            ->filter(fn (MenuCategory $category) => $category->menuItems->isNotEmpty())
            ->values();

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

        $menuJson = Js::from(
            $categories->map(fn (MenuCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'items' => $category->menuItems->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => (float) $item->price,
                    'step' => $item->unit()->step(),
                    // Shipped with the menu so choosing options costs no round
                    // trip - the till has to stay usable on a bad connection.
                    'groups' => $item->modifierGroups->map(fn ($group) => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'single' => $group->isSingle(),
                        'required' => (bool) $group->is_required,
                        'min' => $group->minSelections(),
                        'max' => $group->maxSelections(),
                        'options' => $group->modifiers->map(fn ($modifier) => [
                            'id' => $modifier->id,
                            'name' => $modifier->name,
                            'delta' => (float) $modifier->price_delta,
                            'default' => (bool) $modifier->is_default,
                        ])->values(),
                    ])->values(),
                ])->values(),
            ])->values()
        );

        $constantLists = ConstantList::with(['items' => fn ($query) => $query->orderBy('position')])
            ->orderBy('title')
            ->get()
            ->filter(fn (ConstantList $list) => $list->items->isNotEmpty())
            ->map(fn (ConstantList $list) => [
                'title' => $list->title,
                'values' => $list->items->pluck('value')->all(),
            ])
            ->values();

        return view('transfers.index', [
            'categories' => $categories,
            'accounts' => $accounts,
            'menuJson' => $menuJson,
            'customerNames' => $customerNames,
            'constantLists' => $constantLists,
        ]);
    }

    public function store(StoreTransferRequest $request): RedirectResponse
    {
        $member = auth('tenant')->user();

        if (! $member?->hasPermission(PermissionKey::TransfersCreate)) {
            abort(403);
        }

        try {
            $transfer = $this->service->create($request->validated());
        } catch (Throwable $e) {
            // A broken option selection - a required size skipped, two picked
            // where one is allowed - is the cashier's to fix, not a 500.
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('transfers.created', $transfer->id);
    }

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
