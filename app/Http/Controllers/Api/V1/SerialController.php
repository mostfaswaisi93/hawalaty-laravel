<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\SerialNumberResource;
use App\Models\Tenant\SerialNumber;
use App\Services\Tenant\SerialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SerialController extends Controller
{
    public function __construct(private readonly SerialService $serials) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('search', ''));

        // A partial serial is how anyone actually searches - the last four
        // digits off a sticker, read out over a counter.
        $units = $search === ''
            ? SerialNumber::query()->with(['item', 'batch', 'location'])->latest()->limit(50)->get()
            : $this->serials->lookup($search);

        return SerialNumberResource::collection($units);
    }

    public function show(string $serial): JsonResponse
    {
        $unit = SerialNumber::with(['item', 'batch', 'location', 'customer', 'claims'])->findOrFail($serial);

        return response()->json([
            'data' => array_merge(
                (new SerialNumberResource($unit))->toArray(request()),
                [
                    'claims' => $unit->claims->map(fn ($claim) => [
                        'id' => (string) $claim->id,
                        'reference' => $claim->reference,
                        'status' => $claim->status->value,
                        'issue' => $claim->issue,
                        'reportedAt' => $claim->reported_at?->toIso8601String(),
                    ]),
                ],
            ),
        ]);
    }
}
