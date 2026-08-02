<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\SerialNumberResource;
use App\Http\Resources\Tenant\WarrantyClaimResource;
use App\Services\Tenant\SerialService;
use App\Services\Tenant\WarrantyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Throwable;

class WarrantyController extends Controller
{
    public function __construct(
        private readonly WarrantyService $warranty,
        private readonly SerialService $serials,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return WarrantyClaimResource::collection(
            $this->warranty->list($request->query('status'), $request->query('search'), 20)
        );
    }

    /**
     * Is this unit still covered?
     *
     * The question the counter is actually asked, answered from the unit's own
     * dates rather than from an item-level policy - two identical handsets sold
     * six months apart are covered differently and always were.
     */
    public function lookup(Request $request): JsonResponse
    {
        $serial = trim((string) $request->query('serial', ''));

        if ($serial === '') {
            return response()->json(['message' => __('أدخل الرقم التسلسلي.')], 422);
        }

        $units = $this->serials->lookup($serial);

        return response()->json(['data' => SerialNumberResource::collection($units)->resolve()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'serialId' => ['nullable', 'string', 'exists:tenant.serial_numbers,id'],
            'itemId' => ['nullable', 'required_without:serialId', 'string', 'exists:tenant.menu_items,id'],
            'reference' => ['nullable', 'string', 'max:64'],
            'customerName' => ['nullable', 'string', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:50'],
            'issue' => ['required', 'string', 'max:1000'],
            'underWarranty' => ['sometimes', 'boolean'],
        ]);

        try {
            $claim = $this->warranty->open($data);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new WarrantyClaimResource($claim->load(['item', 'serialNumber'])))->response()->setStatusCode(201);
    }
}
