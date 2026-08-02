<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\StockLevelResource;
use App\Models\Tenant\MenuItem;
use App\Services\Tenant\FitmentService;
use App\Services\Tenant\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The parts counter, over HTTP.
 *
 * Read only: fitment data is catalogue maintenance, done deliberately and rarely,
 * and the screen for it already exists. What a phone at a counter needs is the
 * lookup.
 */
class FitmentController extends Controller
{
    public function __construct(
        private readonly FitmentService $fitment,
        private readonly StockService $stock,
    ) {}

    /**
     * Parts that fit a vehicle, with what is on the shelf.
     */
    public function parts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'modelId' => ['required', 'string', 'exists:tenant.vehicle_models,id'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'engine' => ['nullable', 'string', 'max:64'],
        ]);

        $rows = $this->fitment->partsFor(
            $validated['modelId'],
            $validated['year'] ?? null,
            $validated['engine'] ?? null,
        );

        return response()->json([
            'data' => $rows->map(fn (array $row) => array_merge(
                (new StockLevelResource(['item' => $row['item'], 'on_hand' => $row['on_hand']]))->toArray($request),
                [
                    'fitment' => [
                        'model' => $row['fitment']->vehicleModel?->name,
                        'make' => $row['fitment']->vehicleModel?->make?->name,
                        'yearFrom' => $row['fitment']->year_from,
                        'yearTo' => $row['fitment']->year_to,
                        'engine' => $row['fitment']->engine,
                        'notes' => $row['fitment']->notes,
                    ],
                ],
            )),
        ]);
    }

    /**
     * Find a part by any number that has ever named it.
     *
     * Matched on the stripped form, because "0 986 424 815" and "0986424815" are
     * the same number and nobody types them the same way twice.
     */
    public function reference(Request $request): JsonResponse
    {
        $reference = trim((string) $request->query('reference', ''));

        if ($reference === '') {
            return response()->json(['message' => __('أدخل الرقم المرجعي.')], 422);
        }

        $items = $this->fitment->findByReference($reference);
        $onHand = $this->stock->onHandFor($items->pluck('id')->all());

        return response()->json([
            'data' => $items->map(fn (MenuItem $item) => (new StockLevelResource([
                'item' => $item,
                'on_hand' => $onHand[$item->id] ?? 0.0,
            ]))->toArray($request)),
        ]);
    }

    /**
     * What replaced a part, following the chain to the end.
     *
     * A superseded part is not a dead one - it is a part with a successor, and
     * the counter needs the successor, not an apology.
     */
    public function supersessions(string $item): JsonResponse
    {
        $record = MenuItem::findOrFail($item);

        return response()->json([
            'data' => $this->fitment->supersessionChain($record)->map(fn (MenuItem $link) => [
                'id' => (string) $link->id,
                'name' => $link->name,
                'sku' => $link->sku,
                'superseded' => $link->isSuperseded(),
            ]),
        ]);
    }

    public function vehicles(): JsonResponse
    {
        return response()->json([
            'data' => $this->fitment->makes()->map(fn ($make) => [
                'id' => (string) $make->id,
                'name' => $make->name,
                'wmi' => $make->wmi,

                // Years belong to the fitment, not the model: the same model
                // takes different parts in different years, which is the whole
                // reason the year is asked for.
                'models' => $make->models->map(fn ($model) => [
                    'id' => (string) $model->id,
                    'name' => $model->name,
                    'active' => (bool) $model->active,
                    'fitments' => (int) ($model->fitments_count ?? 0),
                ]),
            ]),
        ]);
    }

    /**
     * Decode a VIN to what ISO 3779 actually lets you read off it - which is the
     * region, the manufacturer and the model year, and not the engine.
     */
    public function vin(Request $request): JsonResponse
    {
        $vin = trim((string) $request->query('vin', ''));

        if ($vin === '') {
            return response()->json(['message' => __('أدخل رقم الهيكل.')], 422);
        }

        return response()->json(['data' => $this->fitment->decodeVin($vin)]);
    }
}
