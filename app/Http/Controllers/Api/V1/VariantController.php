<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant\MenuItem;
use App\Services\Tenant\StockService;
use App\Services\Tenant\VariantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Styles and their size-by-colour matrix.
 *
 * A variant IS a MenuItem, with a parent - which is why it has a barcode, a
 * ledger balance and a purchase history like anything else, and why this
 * endpoint returns items rather than some parallel shape a client would then
 * have to reconcile.
 */
class VariantController extends Controller
{
    public function __construct(
        private readonly VariantService $variants,
        private readonly StockService $stock,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $parents = MenuItem::query()
            ->whereHas('variants')
            ->with(['menuCategory', 'variantAxes'])
            ->whereSearch(['name', 'sku'], $request->query('search'))
            ->orderBy('name')
            ->get();

        $stock = $this->variants->stockByParent($parents, $request->query('locationId'));

        return response()->json([
            'data' => $parents->map(fn (MenuItem $parent) => [
                'id' => (string) $parent->id,
                'name' => $parent->name,
                'category' => $parent->menuCategory?->name,
                'price' => (float) $parent->price,
                'axes' => $parent->variantAxes->map(fn ($axis) => [
                    'id' => (string) $axis->id,
                    'title' => $axis->title,
                ]),
                'variants' => $parent->variants()->count(),
                'onHand' => round((float) ($stock[$parent->id] ?? 0), 3),
            ]),
        ]);
    }

    public function show(Request $request, string $item): JsonResponse
    {
        $parent = MenuItem::with('variantAxes')->findOrFail($item);
        $grid = $this->variants->grid($parent, $request->query('locationId'));

        $cell = fn (?array $entry) => $entry === null ? null : [
            'id' => (string) $entry['variant']->id,
            'name' => $entry['variant']->name,
            'sku' => $entry['variant']->sku,
            'barcode' => $entry['variant']->barcode,
            'onHand' => round((float) $entry['on_hand'], 3),
        ];

        return response()->json([
            'data' => [
                'parent' => [
                    'id' => (string) $parent->id,
                    'name' => $parent->name,
                    'price' => (float) $parent->price,
                ],
                'rows' => $grid['rows'],
                'columns' => $grid['columns'],

                // Keyed "rowId|columnId" so a client can address a square
                // directly instead of scanning a list.
                'cells' => collect($grid['cells'])->map($cell),

                // Variants on a third axis have no square on a two-dimensional
                // grid. Listed separately beats silently hidden.
                'loose' => $grid['loose']->map($cell)->values(),
            ],
        ]);
    }
}
