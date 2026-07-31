<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Enums\PermissionKey;
use App\Http\Requests\Web\Settings\UpdateLogoRequest;
use App\Http\Requests\Web\Settings\UpdateProfileRequest;
use App\Services\Tenant\BusinessLogo;
use App\Services\Tenant\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Self-service settings (sidebar permission=null): the current member's own
 * profile + password, plus a read-only business/license info card. Reuses
 * TeamService::update but passes ONLY name/email/password so role/active can
 * never be self-mutated.
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly TeamService $service,
        private readonly BusinessLogo $logo,
    ) {}

    public function index(): View
    {
        $license = app()->bound('currentLicense') ? app('currentLicense') : null;
        $license?->loadMissing('package');

        return view('settings.index', [
            'member' => auth('tenant')->user(),
            'license' => $license,
            'logo' => $this->logo->dataUri(),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $data = array_filter(
            $request->safe()->only(['name', 'email', 'password']),
            fn ($value) => $value !== null && $value !== ''
        );

        $this->service->update(auth('tenant')->user(), $data);

        return redirect()->route('settings.index')->with('success', __('تم تحديث بياناتك بنجاح.'));
    }

    /**
     * The business logo, printed at the top of every receipt. Gated on
     * BanksManage - it is business-wide branding, not a personal preference,
     * and that is the permission the rest of the business vocabulary uses.
     */
    public function updateLogo(UpdateLogoRequest $request): RedirectResponse
    {
        abort_unless($this->canManageBusiness(), 403);

        $this->logo->put($request->file('logo'));

        return redirect()->route('settings.index')->with('success', __('تم تحديث شعار المشروع.'));
    }

    public function destroyLogo(): RedirectResponse
    {
        abort_unless($this->canManageBusiness(), 403);

        $this->logo->delete();

        return redirect()->route('settings.index')->with('success', __('تم حذف الشعار.'));
    }

    private function canManageBusiness(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::BanksManage);
    }
}
