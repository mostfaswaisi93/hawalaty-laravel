<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Http\Controllers\Web\Concerns\ListsAccounts;
use App\Http\Requests\Tenant\TeamMember\StoreTeamMemberRequest;
use App\Http\Requests\Tenant\TeamMember\UpdateTeamMemberRequest;
use App\Http\Requests\Web\Team\PaySalaryRequest;
use App\Http\Requests\Web\Team\UpdateSalaryRequest;
use App\Models\Tenant\TeamMember;
use App\Services\Tenant\RoleService;
use App\Services\Tenant\SalaryService;
use App\Services\Tenant\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeamController extends Controller
{
    use HasPerPage, ListsAccounts;

    public function __construct(
        private readonly TeamService $service,
        private readonly RoleService $roles,
        private readonly SalaryService $salaries,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة الفريق.'));
        }

        $search = $request->query('search');
        $perPage = $this->perPage($request, 20);

        return view('team.index', [
            'members' => $this->service->paginate($search, $perPage),
            'roles' => $this->roles->list(),

            'accounts' => $this->activeAccounts(),
            'salaryHistory' => $this->salaries->history(perPage: 10),
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function updateSalary(UpdateSalaryRequest $request, string $member): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $model = TeamMember::findOrFail($member);
        $data = $request->validated();

        $model->update([
            'salary_amount' => $data['salaryAmount'] ?? null,
            'salary_period' => $data['salaryPeriod'] ?? null,
        ]);

        return redirect()->route('team.index')->with('success', __('تم تحديث الراتب.'));
    }

    public function paySalary(PaySalaryRequest $request, string $member): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $model = TeamMember::findOrFail($member);
        $this->salaries->pay($model, $request->validated());

        return redirect()->route('team.index')->with('success', __('تم صرف الراتب وتسجيله في المصروفات.'));
    }

    public function store(StoreTeamMemberRequest $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->create($request->validated());

        return redirect()->route('team.index')->with('success', __('تمت إضافة العضو بنجاح.'));
    }

    public function update(UpdateTeamMemberRequest $request, string $teamMember): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $this->service->update(TeamMember::findOrFail($teamMember), $request->validated());

        return redirect()->route('team.index')->with('success', __('تم تحديث العضو بنجاح.'));
    }

    public function destroy(string $teamMember): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        try {
            $this->service->delete(auth('tenant')->user(), TeamMember::findOrFail($teamMember));
        } catch (ValidationException $e) {
            return redirect()->route('team.index')->with('error', $e->validator->errors()->first());
        }

        return redirect()->route('team.index')->with('success', __('تم حذف العضو.'));
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::TeamManage);
    }
}
