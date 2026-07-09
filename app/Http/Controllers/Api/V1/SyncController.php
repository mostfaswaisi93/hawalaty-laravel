<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Sync\SyncPushRequest;
use App\Models\Tenant\Device;
use App\Services\Tenant\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(private readonly SyncService $service) {}

    public function push(SyncPushRequest $request): JsonResponse
    {
        $events = $request->validated('events');

        $currentToken = $request->user()?->currentAccessToken();
        $device = $currentToken
            ? Device::where('sanctum_token_id', $currentToken->id)->first()
            : null;

        return response()->json($this->service->push($device, $events));
    }

    public function pull(Request $request): JsonResponse
    {
        $since = (int) $request->query('since', 0);

        $events = $this->service->pull($since);

        return response()->json(
            $events->map(fn ($event) => [
                'serverSequence' => $event->server_sequence,
                'entityType' => $event->entity_type,
                'entityId' => $event->entity_id,
                'operation' => $event->operation->value,
                'payload' => $event->payload,
                'createdAt' => $event->created_at?->toISOString(),
            ])->values()
        );
    }
}
