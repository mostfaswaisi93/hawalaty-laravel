<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Device;
use App\Services\Tenant\DeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * "My devices" — self-scoped list + revoke (sign-out). Sidebar permission=null.
 * The web session guard has no Sanctum access token, so null is passed for
 * currentToken (every row's is_current is therefore false).
 */
class DeviceController extends Controller
{
    public function __construct(private readonly DeviceService $service) {}

    public function index(): View
    {
        return view('devices.index', [
            'devices' => $this->service->list(auth('tenant')->user(), null),
        ]);
    }

    public function destroy(string $device): RedirectResponse
    {
        $model = Device::findOrFail($device);

        // Own device always; any device with DevicesManage. The list is
        // self-scoped, so this normally just guards direct-id tampering.
        abort_unless(
            $model->team_member_id === auth('tenant')->id()
                || auth('tenant')->user()?->hasPermission(PermissionKey::DevicesManage),
            403
        );

        $this->service->revoke($model);

        return redirect()->route('devices.index')->with('success', __('تم إلغاء الجهاز.'));
    }
}
