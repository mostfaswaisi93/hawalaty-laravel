<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Settings\UpdateLogoRequest;
use App\Http\Requests\Web\Settings\UpdateProfileRequest;
use App\Services\Tenant\BusinessLogo;
use App\Services\Tenant\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

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
