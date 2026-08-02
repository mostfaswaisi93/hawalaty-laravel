<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\ServiceResource;
use App\Models\Tenant\TeamMember;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\SchedulingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SchedulingController extends Controller
{
    public function __construct(
        private readonly SchedulingService $scheduling,
        private readonly CatalogNaming $naming,
    ) {}

    /**
     * A day at a time, as a board by resource. A month grid looks impressive
     * and answers nothing a shop asks in the morning.
     */
    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المواعيد.'));
        }

        $date = $request->query('date', now()->toDateString());
        $resources = $this->scheduling->resources();

        return view('scheduling.index', [
            'date' => $date,
            'resources' => $resources,
            'byResource' => $this->scheduling->day($date),
            'services' => MenuItem::query()->where('kind', 'service')->where('active', true)->orderBy('name')->get(),
            'statuses' => AppointmentStatus::cases(),
            'openSlots' => $resources->mapWithKeys(fn (ServiceResource $resource) => [
                $resource->id => $this->scheduling->openSlots($resource->id, $date),
            ]),
            'naming' => $this->naming,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'customerName' => ['required', 'string', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:50'],
            'itemId' => ['nullable', 'string', 'exists:tenant.menu_items,id'],
            'resourceId' => ['nullable', 'string', 'exists:tenant.service_resources,id'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['nullable', 'date'],
            'deposit' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $appointment = $this->scheduling->book($data);
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('scheduling.index', ['date' => $appointment->starts_at->toDateString()])
            ->with('success', __('تم حجز الموعد.'));
    }

    public function update(Request $request, string $appointment): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date'],
        ]);

        $model = Appointment::with('item')->findOrFail($appointment);

        try {
            if (filled($data['startsAt'] ?? null)) {
                $this->scheduling->reschedule($model, $data['startsAt'], $data['endsAt'] ?? null);
            }

            if (filled($data['status'] ?? null)) {
                $this->scheduling->setStatus($model, AppointmentStatus::from($data['status']));
            }
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('تم تحديث الموعد.'));
    }

    /**
     * Technicians, bays and equipment - whatever the work needs and there is
     * only one of.
     */
    public function resources(): View|RedirectResponse
    {
        if (! $this->canManage()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لإدارة المواعيد.'));
        }

        return view('scheduling.resources', [
            'resources' => $this->scheduling->resources(activeOnly: false),
            'members' => TeamMember::query()->where('active', true)->orderBy('name')->get(),
            'kinds' => ServiceResource::kinds(),
        ]);
    }

    public function storeResource(Request $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('tenant.service_resources', 'name')],
            'kind' => ['required', Rule::in(array_keys(ServiceResource::kinds()))],
            'teamMemberId' => ['nullable', 'string', 'exists:tenant.team_members,id'],
        ]);

        ServiceResource::create([
            'name' => $data['name'],
            'kind' => $data['kind'],
            'team_member_id' => $data['teamMemberId'] ?? null,
            'active' => true,
        ]);

        return redirect()->route('scheduling.resources')->with('success', __('تمت إضافة المورد.'));
    }

    public function updateResource(Request $request, string $resource): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'active' => ['nullable', 'boolean'],
        ]);

        ServiceResource::findOrFail($resource)->update(['active' => (bool) ($data['active'] ?? false)]);

        return redirect()->route('scheduling.resources')->with('success', __('تم تحديث المورد.'));
    }

    private function canManage(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::TransfersCreate);
    }
}
