<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates every `/api/v1/admin/*` route (except the login endpoint itself)
 * behind "the authenticated Sanctum user is actually an Admin, not a
 * tenant's TeamMember". Registered as alias `admin` in bootstrap/app.php,
 * always paired with `auth:sanctum` and NEVER with `tenant.header` — admin
 * routes stay on the platform's default `mysql` connection throughout, see
 * BACKEND_PROMPT.md section 8.
 *
 * This check matters because Laravel\Sanctum\HasApiTokens is used by BOTH
 * App\Models\Admin and App\Models\Tenant\TeamMember, and both share the same
 * personal_access_tokens table shape (via TenantPersonalAccessToken) — a
 * valid bearer token alone does not prove the caller is platform staff.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() instanceof Admin, 403, 'Admin access required.');

        return $next($request);
    }
}
