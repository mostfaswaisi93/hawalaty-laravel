<?php

namespace App\Http\Middleware;

use App\Exceptions\InvalidLicenseException;
use App\Services\TenantConnectionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
