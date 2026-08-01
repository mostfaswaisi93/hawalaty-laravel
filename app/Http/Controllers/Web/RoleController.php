<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Role\StoreRoleRequest;
use App\Http\Requests\Tenant\Role\UpdateRoleRequest;
use App\Models\Tenant\Role;
use App\Services\Tenant\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $service) {}

    public function index(): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة الفريق.'));
        }

        return view('roles.index', [

            'roles' => Role::with('permissions')->withCount('teamMembers')->orderByDesc('is_system')->orderBy('name')->get(),
            'permissions' => PermissionKey::cases(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->create($request->validated());

        return redirect()->route('roles.index')->with('success', __('تم إنشاء الدور.'));
    }

    public function update(UpdateRoleRequest $request, string $role): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        try {
            $this->service->update(Role::findOrFail($role), $request->validated());
        } catch (ValidationException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('roles.index')->with('success', __('تم تحديث الدور.'));
    }

    public function destroy(string $role): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        try {
            $this->service->delete(Role::findOrFail($role));
        } catch (ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('roles.index')->with('success', __('تم حذف الدور.'));
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::TeamManage);
    }
}
