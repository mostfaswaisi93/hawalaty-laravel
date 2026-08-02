<?php

namespace App\Http\Controllers\Web\Auth;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Exceptions\InvalidLicenseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\TenantLoginRequest;
use App\Http\Requests\Web\Auth\TenantSignupRequest;
use App\Models\Tenant\TeamMember;
use App\Services\Tenant\AuditLogger;
use App\Services\Tenant\DeviceRegistrar;
use App\Services\TenantConnectionResolver;
use App\Services\TenantProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class TenantAuthController extends Controller
{
    public function __construct(private readonly TenantProvisioner $provisioner) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(TenantLoginRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $license = TenantConnectionResolver::resolve($data['licenseCode']);
        } catch (InvalidLicenseException) {
            return $this->invalidCredentials($request);
        }

        app()->instance('currentLicense', $license);

        try {
            $attempted = Auth::guard('tenant')->attempt([
                'email' => $data['email'],
                'password' => $data['password'],
                'active' => true,
            ], (bool) ($data['remember'] ?? false));

            if (! $attempted) {
                return $this->invalidCredentials($request);
            }
        } catch (Throwable $e) {
            Log::error('Tenant web login failed unexpectedly.', [
                'licenseCode' => $data['licenseCode'],
                'exception' => $e,
            ]);

            return $this->invalidCredentials($request);
        }

        $request->session()->regenerate();
        $request->session()->put('license_code', $license->license_code);

        $member = Auth::guard('tenant')->user();
        AuditLogger::record(AuditAction::LoggedIn, AuditEntityType::TeamMember, $member->name, AuditCategory::Auth);

        // After regenerate(), so the row is keyed on the session id the browser
        // will actually send back.
        app(DeviceRegistrar::class)->registerSession($member, $request, $license);

        return redirect()->intended(route('dashboard'));
    }

    public function showSignup(): View
    {
        return view('auth.signup');
    }

    public function signup(TenantSignupRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $license = $this->provisioner->provision([
            'businessName' => $data['businessName'],
            'ownerName' => $data['ownerName'],
            'ownerEmail' => $data['ownerEmail'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'packageId' => $data['packageId'] ?? null,
        ]);

        TenantConnectionResolver::resolve($license->license_code);
        app()->instance('currentLicense', $license);

        $owner = TeamMember::where('email', $data['ownerEmail'])->firstOrFail();

        Auth::guard('tenant')->login($owner);

        $request->session()->regenerate();
        $request->session()->put('license_code', $license->license_code);

        return redirect()->route('dashboard');
    }

    public function logout(): RedirectResponse
    {
        // Before invalidate(), which rotates the id this row is keyed on.
        app(DeviceRegistrar::class)->forgetSession(request());

        Auth::guard('tenant')->logout();

        request()->session()->forget('license_code');
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function invalidCredentials(TenantLoginRequest $request): RedirectResponse
    {
        return redirect()->route('login')
            ->withInput($request->only('licenseCode', 'email'))
            ->withErrors(['login' => __('رمز الترخيص أو البريد الإلكتروني أو كلمة المرور غير صحيحة.')]);
    }
}
