<?php

namespace App\Http\Middleware;

use App\Enums\BusinessCapability;
use App\Services\Tenant\BusinessProfile;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route on the workspace's vertical.
 *
 * Sibling of EnsurePackageFeature, and deliberately separate from it: a package
 * feature is something the operator sells, a capability is something the trade
 * requires. A pharmacy needs batch tracking on every tier; a bookshop needs it on
 * none. Accepts several capabilities and passes when any one of them is on.
 */
class EnsureBusinessCapability
{
    public function handle(Request $request, Closure $next, string ...$capabilities): Response
    {
        $cases = array_map(function (string $capability): BusinessCapability {
            $case = BusinessCapability::tryFrom($capability);

            if (! $case instanceof BusinessCapability) {
                throw new InvalidArgumentException("Unknown business capability [{$capability}].");
            }

            return $case;
        }, $capabilities);

        if (app(BusinessProfile::class)->hasAny($cases)) {
            return $next($request);
        }

        abort(403, __('هذه الوحدة غير مفعّلة لتصنيف نشاطك.'));
    }
}
