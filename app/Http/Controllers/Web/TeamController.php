<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\TeamMember\StoreTeamMemberRequest;
use App\Http\Requests\Tenant\TeamMember\UpdateTeamMemberRequest;
use App\Models\Tenant\TeamMember;
use App\Services\Tenant\RoleService;
use App\Services\Tenant\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __construct(
        private readonly TeamService $service,
        private readonly RoleService $roles,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', 'ليست لديك صلاحية لإدارة الفريق.');
        }

        $search = $request->query('search');

        return view('team.index', [
            'members' => $this->service->list($search),
            'roles' => $this->roles->list(),
            'search' => $search,
        ]);
    }

    public function store(StoreTeamMemberRequest $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->create($request->validated());

        return redirect()->route('team.index')->with('success', 'تمت إضافة العضو بنجاح.');
    }

    // Route param name is {teamMember} so UpdateTeamMemberRequest's
    // Rule::unique()->ignore($this->route('teamMember')) excludes this row.
    public function update(UpdateTeamMemberRequest $request, string $teamMember): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->update(TeamMember::findOrFail($teamMember), $request->validated());

        return redirect()->route('team.index')->with('success', 'تم تحديث العضو بنجاح.');
    }

    public function destroy(string $teamMember): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        try {
            // delete() guards against removing yourself / the last owner and
            // throws ValidationException with an Arabic-friendly message.
            $this->service->delete(auth('tenant')->user(), TeamMember::findOrFail($teamMember));
        } catch (ValidationException $e) {
            return redirect()->route('team.index')->with('error', $e->validator->errors()->first());
        }

        return redirect()->route('team.index')->with('success', 'تم حذف العضو.');
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::TeamManage);
    }
}
