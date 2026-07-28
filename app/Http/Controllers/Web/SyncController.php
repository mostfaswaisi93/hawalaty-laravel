<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Sync\SyncPushRequest;
use App\Services\Tenant\SyncService;
use Illuminate\Http\JsonResponse;

/**
 * The browser's drain for orders taken while the connection was down.
 *
 * Why this exists next to Api\V1\SyncController rather than reusing it: the web
 * app authenticates with a SESSION COOKIE, while the API route demands a Sanctum
 * bearer token and an X-License-Code header. The only way a page could call the
 * API version would be to keep a long-lived token in the browser, which is an
 * XSS-readable credential this app has no reason to create.
 *
 * The engine is identical - both controllers call the same SyncService - so the
 * two surfaces cannot diverge on idempotency or on how an event is applied.
 *
 * There is no `pull` here. A browser reloads the page to see server state; the
 * pull cursor exists for a native client keeping its own local database.
 */
class SyncController extends Controller
{
    public function __construct(private readonly SyncService $service) {}

    public function push(SyncPushRequest $request): JsonResponse
    {
        $member = auth('tenant')->user();

        // The same permission the online path requires. A queued order must
        // never become a way around a permission the member does not hold -
        // being offline when it was written changes nothing about who may
        // write it.
        abort_unless($member?->hasPermission(PermissionKey::TransfersCreate), 403);

        // No device: the browser is not a registered client, and sync_events
        // records that honestly rather than inventing one.
        return response()->json($this->service->push(null, $request->validated('events')));
    }
}
