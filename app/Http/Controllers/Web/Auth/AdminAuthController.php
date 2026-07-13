<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\AdminLoginRequest;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Session/cookie login for the Super Admin portal — deliberately simpler
 * than TenantAuthController: no license code, no dynamic `tenant`
 * connection, Admin always lives on the default `mysql` connection. Mirrors
 * App\Http\Controllers\Api\V1\Admin\AuthController.
 */
class AdminAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.admin-login');
    }

    public function login(AdminLoginRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $attempted = Auth::guard('admin')->attempt([
            'email' => $data['email'],
            'password' => $data['password'],
        ], (bool) ($data['remember'] ?? false));

        if (! $attempted) {
            return redirect()->route('admin.login')
                ->withInput($request->only('email'))
                ->withErrors(['login' => __('البريد الإلكتروني أو كلمة المرور غير صحيحة.')]);
        }

        $request->session()->regenerate();

        $admin = Auth::guard('admin')->user();
        ActivityLog::create([
            'admin_id' => $admin->id,
            'action' => 'logged_in',
            'entity_type' => 'admin',
            'entity_label' => $admin->name,
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(): RedirectResponse
    {
        Auth::guard('admin')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
