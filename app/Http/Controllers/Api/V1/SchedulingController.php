<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\AppointmentResource;
use App\Http\Resources\Tenant\WorkOrderResource;
use App\Models\Tenant\WorkOrder;
use App\Services\Tenant\SchedulingService;
use App\Services\Tenant\WorkOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class SchedulingController extends Controller
{
    public function __construct(
        private readonly SchedulingService $scheduling,
        private readonly WorkOrderService $workOrders,
    ) {}

    /**
     * One day's diary.
     *
     * A day rather than a range: a resource can only be in one place at a time,
     * and the overlap that matters is the one inside a single day.
     */
    public function appointments(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'resourceId' => ['nullable', 'string', 'exists:tenant.service_resources,id'],
        ]);

        $date = $validated['date'] ?? Carbon::today()->toDateString();

        return AppointmentResource::collection(
            $this->scheduling->day($date, $validated['resourceId'] ?? null)
        );
    }

    /**
     * When a resource is free, derived from what is already booked rather than
     * from a stored calendar there would then be two of.
     */
    public function slots(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'resourceId' => ['required', 'string', 'exists:tenant.service_resources,id'],
            'date' => ['required', 'date'],
        ]);

        return response()->json([
            'data' => $this->scheduling->openSlots($validated['resourceId'], $validated['date']),
        ]);
    }

    public function resources(): JsonResponse
    {
        return response()->json([
            'data' => $this->scheduling->resources()->map(fn ($resource) => [
                'id' => (string) $resource->id,
                'name' => $resource->name,
                'kind' => $resource->kind,
                'active' => (bool) $resource->active,
            ]),
        ]);
    }

    public function workOrders(Request $request): AnonymousResourceCollection
    {
        return WorkOrderResource::collection(
            $this->workOrders->list($request->query('status'), $request->query('search'), 20)
        );
    }

    public function workOrder(string $workOrder): JsonResponse
    {
        $record = WorkOrder::with(['lines', 'resource', 'customer'])->findOrFail($workOrder);

        return (new WorkOrderResource($record))->response();
    }
}
