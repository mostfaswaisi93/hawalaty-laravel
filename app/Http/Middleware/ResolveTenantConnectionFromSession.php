<?php

namespace App\Http\Middleware;

use App\Exceptions\InvalidLicenseException;
use App\Services\TenantConnectionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session-cookie equivalent of ResolveTenantConnection: a browser carries no
 * `X-License-Code` header, so the code is read from `session('license_code')`,
 * written by TenantAuthController on login.
 *
 * Register on tenant-space web routes only, always AHEAD of `auth:tenant`
 * (alias `tenant.session`). Never on `/admin/*`.
 *
 * No license_code yet passes through - `auth:tenant` then redirects the guest.
 * A license that has since become invalid/suspended/expired tears the session
 * down and bounces to the "license no longer active" page, rather than letting
 * a stale `tenant` connection blow up the next query.
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
            // Deliberately does NOT call Auth::guard('tenant')->logout() -
            // empirically caught during this task's verification: the
            // `tenant` guard's own logout() first calls user() to resolve
            // who's currently logged in, which queries `team_members` on
            // the `tenant` connection (see TeamMember::getConnectionName())
            // - but that connection was never configured this request,
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
