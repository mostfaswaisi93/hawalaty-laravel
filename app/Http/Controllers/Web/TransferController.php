<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Transfer\StoreTransferRequest;
use App\Models\Tenant\Bank;
use App\Models\Tenant\MenuCategory;
use App\Services\Tenant\TransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Js;
use Illuminate\View\View;

/**
 * Server-rendered "New Order" builder — same TransferService and
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

        $banks = Bank::query()->where('active', true)->where('type', 'bank')->orderBy('name')->get(['id', 'name']);
        $wallets = Bank::query()->where('active', true)->where('type', 'wallet')->orderBy('name')->get(['id', 'name']);

        // Handed to Alpine as a JSON blob (x-data="orderBuilder(...)") so the
        // menu browser/search/category filter runs entirely client-side
        // without extra requests — see BACKEND_PROMPT-style brief for this
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
            'banks' => $banks,
            'wallets' => $wallets,
            'menuJson' => $menuJson,
        ]);
    }

    public function store(StoreTransferRequest $request): RedirectResponse
    {
        $member = auth('tenant')->user();

        if (! $member?->hasPermission(PermissionKey::TransfersCreate)) {
            abort(403);
        }

        $this->service->create($request->validated());

        return redirect()->route('transfers.index')->with('success', __('تم إنشاء الطلب بنجاح.'));
    }
}
