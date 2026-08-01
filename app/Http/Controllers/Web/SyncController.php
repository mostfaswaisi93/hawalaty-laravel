<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Sync\SyncPushRequest;
use App\Services\Tenant\SyncService;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function __construct(private readonly SyncService $service) {}

    public function push(SyncPushRequest $request): JsonResponse
    {
        $member = auth('tenant')->user();

        abort_unless($member?->hasPermission(PermissionKey::TransfersCreate), 403);

        return response()->json($this->service->push(null, $request->validated('events')));
    }
}
