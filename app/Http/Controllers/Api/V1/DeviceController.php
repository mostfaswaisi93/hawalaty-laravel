<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\DeviceResource;
use App\Models\Tenant\Device;
use App\Services\Tenant\DeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeviceController extends Controller
{
    public function __construct(private readonly DeviceService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $currentToken = $request->user()->currentAccessToken();

        $devices = $this->service->list($request->user(), $currentToken);

        return DeviceResource::collection($devices);
    }

    public function destroy(string $device): JsonResponse
    {
        $deviceModel = Device::findOrFail($device);

        $this->authorize('delete', $deviceModel);

        $this->service->revoke($deviceModel);

        return response()->json(null, 204);
    }
}
