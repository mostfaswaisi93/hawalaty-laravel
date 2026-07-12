<?php

namespace App\Http\Middleware;

use App\Exceptions\InvalidLicenseException;
use App\Services\TenantConnectionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session-cookie equivalent of App\Http\Middleware\ResolveTenantConnection —
 * see BACKEND_PROMPT.md section 8.1 and this task's report for why a
 * browser session needs its own version of the same "which tenant database"
 * chicken-and-egg problem Sanctum's header-based flow already solves.
 *
 * A browser session carries no `X-License-Code` header, so the license code
 * is instead read from `session('license_code')` — written by
 * App\Http\Controllers\Web\Auth\TenantAuthController on successful login.
 * Register on tenant-space web route groups only, always ahead of
 * `auth:tenant` (alias: `tenant.session`), exactly like the header version
 * is always ahead of `auth:sanctum`. Never applied to `/admin/*` routes.
 *
 * If no license_code is in the session yet, this simply passes through —
 * the `auth:tenant` middleware immediately after it will redirect the guest
 * to the login page. If a license_code IS present but has since become
 * invalid/suspended/expired (could happen between page loads — an admin
 * suspended it, or it expired), the tenant session is torn down and the
 * user is bounced to a dedicated "license no longer active" page instead of
 * letting a stale/garbage `tenant` connection blow up the next query.
 */
class ResolveTenantConnectionFromSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $licenseCode = $request->session()->get('license_code');

        if (! $licenseCode) {
            return $next($request);
        }

        try {
            $license = TenantConnectionResolver::resolve($licenseCode);
        } catch (InvalidLicenseException) {
            // Deliberately does NOT call Auth::guard('tenant')->logout() —
            // empirically caught during this task's verification: the
            // `tenant` guard's own logout() first calls user() to resolve
            // who's currently logged in, which queries `team_members` on
            // the `tenant` connection (see TeamMember::getConnectionName())
            // — but that connection was never configured this request,
            // because TenantConnectionResolver::resolve() threw BEFORE
            // reaching the config(['database.connections.tenant' => ...])
            // call. That turned "license just went inactive" into an
            // unrelated 500 ("Access denied for user ''@'localhost'")
            // instead of the intended redirect. Invalidating the whole
            // session (which wipes the tenant guard's stored user id along
            // with everything else, no DB query required) achieves the same
            // "log the user out" outcome without touching the now-unreachable
            // tenant database at all.
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('license.inactive');
        }

        app()->instance('currentLicense', $license);

        return $next($request);
    }
}
