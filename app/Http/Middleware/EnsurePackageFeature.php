<?php

namespace App\Http\Middleware;

use App\Enums\PackageFeature;
use App\Models\License;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePackageFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $case = PackageFeature::tryFrom($feature);

        if (! $case instanceof PackageFeature) {
            throw new \InvalidArgumentException("Unknown package feature [{$feature}].");
        }

        $license = app()->bound('currentLicense') ? app('currentLicense') : null;

        if ($license instanceof License && $license->hasFeature($case)) {
            return $next($request);
        }

        abort(403, __('هذه الميزة غير متاحة في باقتك الحالية.'));
    }
}
