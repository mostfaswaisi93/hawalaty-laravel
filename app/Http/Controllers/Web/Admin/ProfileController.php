<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Profile\UpdateAdminProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The Super Admin's own account page - distinct from admin/settings, which
 * holds platform-wide key/value configuration rather than anything personal.
 * Mirrors the tenant-side App\Http\Controllers\Web\SettingsController.
 */
class ProfileController extends Controller
{
    public function index(): View
    {
        return view('admin.profile', ['admin' => auth('admin')->user()]);
    }

    public function update(UpdateAdminProfileRequest $request): RedirectResponse
    {
        $admin = auth('admin')->user();

        // An empty password field means "keep the current one" - filtering
        // before fill() stops a blank from being hashed into the column.
        $data = array_filter(
            $request->safe()->only(['name', 'email', 'password']),
            fn ($value) => $value !== null && $value !== ''
        );

        $admin->fill($data)->save();

        return redirect()->route('admin.profile.index')->with('success', __('تم تحديث بياناتك بنجاح.'));
    }
}
