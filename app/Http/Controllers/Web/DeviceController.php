<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Device;
use App\Services\Tenant\DeviceRegistrar;
use App\Services\Tenant\DeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function __construct(
        private readonly DeviceService $service,
        private readonly DeviceRegistrar $registrar,
    ) {}

    public function index(): View
    {
        $member = auth('tenant')->user();
        $manages = (bool) $member?->hasPermission(PermissionKey::DevicesManage);

        return view('devices.index', [
            'devices' => $manages
                ? $this->service->listForTenant(null)
                : $this->service->list($member, null),
            'usage' => $this->registrar->usage(),
            'manages' => $manages,
        ]);
    }

    public function destroy(string $device): RedirectResponse
    {
        $model = Device::findOrFail($device);

        abort_unless(
            $model->team_member_id === auth('tenant')->id()
                || auth('tenant')->user()?->hasPermission(PermissionKey::DevicesManage),
            403
        );

        $this->service->revoke($model);

        return redirect()->route('devices.index')->with('success', __('تم إلغاء الجهاز.'));
    }
}
