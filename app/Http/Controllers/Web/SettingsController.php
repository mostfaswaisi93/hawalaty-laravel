<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Settings\UpdateProfileRequest;
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
    public function __construct(private readonly TeamService $service) {}

    public function index(): View
    {
        $license = app()->bound('currentLicense') ? app('currentLicense') : null;
        $license?->loadMissing('package');

        return view('settings.index', [
            'member' => auth('tenant')->user(),
            'license' => $license,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $data = array_filter(
            $request->safe()->only(['name', 'email', 'password']),
            fn ($value) => $value !== null && $value !== ''
        );

        $this->service->update(auth('tenant')->user(), $data);

        return redirect()->route('settings.index')->with('success', 'تم تحديث بياناتك بنجاح.');
    }
}
