<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ConstantList\StoreConstantListItemRequest;
use App\Http\Requests\Tenant\ConstantList\StoreConstantListRequest;
use App\Models\Tenant\ConstantList;
use App\Services\Tenant\ConstantListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ConstantController extends Controller
{
    public function __construct(private readonly ConstantListService $service) {}

    public function index(): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', 'ليست لديك صلاحية لإدارة الثوابت.');
        }

        $lists = ConstantList::query()
            ->with(['items' => fn ($query) => $query->orderBy('position')])
            ->orderBy('title')
            ->get();

        return view('constants.index', ['lists' => $lists]);
    }

    public function store(StoreConstantListRequest $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->create($request->validated());

        return redirect()->route('constants.index')->with('success', 'تمت إضافة القائمة بنجاح.');
    }

    public function destroy(string $list): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->delete(ConstantList::findOrFail($list));

        return redirect()->route('constants.index')->with('success', 'تم حذف القائمة.');
    }

    public function storeItem(StoreConstantListItemRequest $request, string $list): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->addItem(ConstantList::findOrFail($list), $request->validated());

        return redirect()->route('constants.index')->with('success', 'تمت إضافة العنصر.');
    }

    public function destroyItem(string $list, string $item): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->deleteItem(ConstantList::findOrFail($list), $item);

        return redirect()->route('constants.index')->with('success', 'تم حذف العنصر.');
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::ConstantsManage);
    }
}
