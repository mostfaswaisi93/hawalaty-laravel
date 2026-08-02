<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Modifier;
use App\Models\Tenant\ModifierGroup;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\ModifierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ModifierController extends Controller
{
    public function __construct(
        private readonly ModifierService $service,
        private readonly CatalogNaming $naming,
    ) {}

    public function index(): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة القائمة.'));
        }

        return view('modifiers.index', [
            'groups' => $this->service->groups(),
            'items' => MenuItem::query()->with('modifierGroups')->where('kind', 'goods')->orderBy('name')->get(),
            'naming' => $this->naming,
        ]);
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->createGroup($this->validateGroup($request));

        return redirect()->route('modifiers.index')->with('success', __('تمت إضافة مجموعة الخيارات.'));
    }

    public function updateGroup(Request $request, string $group): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $model = ModifierGroup::findOrFail($group);

        $this->service->updateGroup($model, $this->validateGroup($request, $model->id));

        return redirect()->route('modifiers.index')->with('success', __('تم تحديث مجموعة الخيارات.'));
    }

    public function destroyGroup(string $group): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        ModifierGroup::findOrFail($group)->delete();

        return redirect()->route('modifiers.index')->with('success', __('تم حذف مجموعة الخيارات.'));
    }

    public function storeModifier(Request $request, string $group): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $model = ModifierGroup::findOrFail($group);

        $this->service->createModifier($model, $this->validateModifier($request, $model->id));

        return redirect()->route('modifiers.index')->with('success', __('تمت إضافة الخيار.'));
    }

    public function updateModifier(Request $request, string $modifier): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $model = Modifier::findOrFail($modifier);

        $this->service->updateModifier($model, $this->validateModifier($request, $model->modifier_group_id, $model->id));

        return redirect()->route('modifiers.index')->with('success', __('تم تحديث الخيار.'));
    }

    public function destroyModifier(string $modifier): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        Modifier::findOrFail($modifier)->delete();

        return redirect()->route('modifiers.index')->with('success', __('تم حذف الخيار.'));
    }

    /**
     * Attach groups to the items that offer them.
     */
    public function syncItem(Request $request, string $item): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'groupIds' => ['nullable', 'array'],
            'groupIds.*' => ['string', 'exists:tenant.modifier_groups,id'],
        ]);

        $this->service->syncItemGroups(MenuItem::findOrFail($item), $data['groupIds'] ?? []);

        return redirect()->route('modifiers.index')->with('success', __('تم ربط الخيارات بالصنف.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateGroup(Request $request, ?string $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('tenant.modifier_groups', 'name')->ignore($ignoreId)],
            'selection' => ['required', Rule::in(['single', 'multiple'])],
            'isRequired' => ['nullable', 'boolean'],
            'minSelect' => ['nullable', 'integer', 'min:0', 'max:20'],
            'maxSelect' => ['nullable', 'integer', 'min:1', 'max:20'],
            'position' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateModifier(Request $request, string $groupId, ?string $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('tenant.modifiers', 'name')->where('modifier_group_id', $groupId)->ignore($ignoreId),
            ],
            'priceDelta' => ['nullable', 'numeric', 'min:-99999', 'max:99999'],
            'componentItemId' => ['nullable', 'string', 'exists:tenant.menu_items,id'],
            'componentQuantity' => ['nullable', 'required_with:componentItemId', 'numeric', 'gt:0'],
            'isDefault' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ], [
            'componentQuantity.required_with' => __('حدّد الكمية المستهلكة من المكوّن.'),
        ]);
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::MenuManage);
    }
}
