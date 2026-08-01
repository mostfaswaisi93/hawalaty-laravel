<?php

namespace App\Http\Middleware;

use App\Enums\PackageFeature;
use App\Models\License;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a route whose feature the tenant's package does not include.
 *
 * WHY THIS EXISTS
 * PackageFeature listed ten features and the packages page let an operator tick
 * them per package - but only WhatsApp was ever checked anywhere. Every other
 * box was decoration: a Basic tenant, sold three features, could reach Devices,
 * Backups and Constants by typing the URL, and the sidebar showed them too.
 * Either the boxes had to go or they had to mean something; they now mean
 * something.
 *
 * Hiding the nav entry is not enough on its own. layouts/app.blade.php already
 * filters the rail by `hasFeature`, but a rail is an affordance - a bookmark, a
 * shared link or a typed path walks straight past it. This is the actual gate.
 *
 * A missing license CLOSES the feature rather than opening it, matching the
 * sidebar's own comment: the app must never hand out what the package does not
 * sell just because the license could not be resolved.
 *
 * Usage: ->middleware('package:menu')
 */
class EnsurePackageFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $case = PackageFeature::tryFrom($feature);

        // An unknown key is a routing mistake, not a tenant problem. Failing
        // closed would lock a page nobody meant to gate, and failing open would
        // hide the typo forever - so it is loud.
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
