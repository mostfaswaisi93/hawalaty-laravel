<?php

namespace App\Http\Middleware;

use App\Exceptions\InvalidLicenseException;
use App\Services\TenantConnectionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Points the dynamic `tenant` database connection at the correct tenant
 * database for this request, BEFORE `auth:sanctum` runs — see
 * BACKEND_PROMPT.md section 8.1. Register on tenant-space route groups only,
 * always ahead of `auth:sanctum` in the middleware order (alias:
 * `tenant.header`). Never applied to `/admin/*` (Super Admin) routes, which
 * stay on the platform's default connection.
 *
 * Reads the `X-License-Code` header (every tenant request except login,
 * which reads `licenseCode` from its body and calls
 * TenantConnectionResolver::resolve() directly instead of going through
 * this middleware). On success, binds the resolved License into the
 * container as `currentLicense` — this is also what
 * App\Models\TenantPersonalAccessToken::getConnectionName() checks to
 * decide whether Sanctum token lookups should hit the `tenant` connection.
 */
class ResolveTenantConnection
{
    public function handle(Request $request, Closure $next): Response
    {
        $licenseCode = $request->header('X-License-Code');

        if (! $licenseCode) {
            return response()->json([
                'message' => 'The X-License-Code header is required.',
            ], 422);
        }

        try {
            $license = TenantConnectionResolver::resolve($licenseCode);
        } catch (InvalidLicenseException $e) {
            return response()->json([
                'message' => 'Invalid or inactive license.',
            ], 403);
        }

        app()->instance('currentLicense', $license);

        return $next($request);
    }
}
