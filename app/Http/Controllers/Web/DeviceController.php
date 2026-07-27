<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Device;
use App\Services\Tenant\DeviceRegistrar;
use App\Services\Tenant\DeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Linked devices + revoke (sign-out).
 *
 * Two scopes on one page: a member sees their own devices, while a member with
 * DevicesManage sees every device on the license. That mirrors the allowance
 * itself, which is license-wide (`packages.device_limit`) rather than
 * per-member - whoever can free up a slot has to be able to see who holds them.
 *
 * The web session guard has no Sanctum access token, so null is passed for
 * currentToken and no row is marked "current" here.
 */
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

        // Own device always; any device with DevicesManage. The personal list
        // is self-scoped, so this normally just guards direct-id tampering.
        abort_unless(
            $model->team_member_id === auth('tenant')->id()
                || auth('tenant')->user()?->hasPermission(PermissionKey::DevicesManage),
            403
        );

        $this->service->revoke($model);

        return redirect()->route('devices.index')->with('success', __('تم إلغاء الجهاز.'));
    }
}
