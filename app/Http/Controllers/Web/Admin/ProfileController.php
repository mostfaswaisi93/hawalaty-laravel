<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Profile\UpdateAdminProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(): View
    {
        return view('admin.profile', ['admin' => auth('admin')->user()]);
    }

    public function update(UpdateAdminProfileRequest $request): RedirectResponse
    {
        $admin = auth('admin')->user();

        $data = array_filter(
            $request->safe()->only(['name', 'email', 'password']),
            fn ($value) => $value !== null && $value !== ''
        );

        $admin->fill($data)->save();

        return redirect()->route('admin.profile.index')->with('success', __('تم تحديث بياناتك بنجاح.'));
    }
}
