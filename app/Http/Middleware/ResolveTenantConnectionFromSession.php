<?php

namespace App\Http\Middleware;

use App\Exceptions\InvalidLicenseException;
use App\Services\TenantConnectionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('license.inactive');
        }

        app()->instance('currentLicense', $license);

        return $next($request);
    }
}
