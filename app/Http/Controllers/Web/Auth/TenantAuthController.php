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
use App\Services\TenantConnectionResolver;
use App\Services\TenantProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Session/cookie login for tenant space (a license's team members) — the
 * web-page counterpart of App\Http\Controllers\Api\V1\Auth\AuthController.
 * See BACKEND_PROMPT.md sections 5 and 8.1, and this task's report for the
 * session-specific chicken-and-egg fix
 * (App\Http\Middleware\ResolveTenantConnectionFromSession).
 */
class TenantAuthController extends Controller
{
    public function __construct(private readonly TenantProvisioner $provisioner) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * No `tenant.session` middleware runs ahead of this route (there's no
     * session license_code yet on a guest's first visit), so — exactly like
     * the API login endpoint — TenantConnectionResolver is called directly
     * here with the license code straight from the submitted form, before
     * anything touches the `tenant` connection.
     */
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
            // `active` is included as a credential (not checked after the
            // fact) so an inactive team member fails the SAME generic
            // "invalid credentials" path as a wrong password — never a
            // different message that would leak the account exists but is
            // disabled.
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

        // provision() already leaves the `tenant` connection pointed at the
        // new database, but resolve explicitly here too so login-after-signup
        // doesn't silently depend on that side effect (same defensive
        // re-resolve the API's AuthController::signup() does).
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
        Auth::guard('tenant')->logout();

        request()->session()->forget('license_code');
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * One generic error for every login failure reason (bad license code,
     * unknown email, wrong password, inactive account) — never reveals
     * which part was wrong. Password is deliberately excluded from the
     * flashed input.
     */
    private function invalidCredentials(TenantLoginRequest $request): RedirectResponse
    {
        return redirect()->route('login')
            ->withInput($request->only('licenseCode', 'email'))
            ->withErrors(['login' => __('رمز الترخيص أو البريد الإلكتروني أو كلمة المرور غير صحيحة.')]);
    }
}
