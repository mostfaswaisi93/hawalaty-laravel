<?php

namespace App\Services\Tenant;

use App\Enums\BusinessCapability;
use App\Models\Tenant\ItemFitment;
use App\Models\Tenant\ItemReference;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\VehicleMake;
use App\Models\Tenant\VehicleModel;
use App\Support\Vin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Answering "does this fit".
 *
 * Two ways in, because a counter gets asked both. Either the customer knows the
 * car - make, model, year - or they are holding the old part with a number on
 * it. The second is the harder one: that number is as likely to be the vehicle
 * manufacturer's as any supplier's, and it will be written with whatever
 * spacing and dashes were on the box.
 */
class FitmentService
{
    /**
     * A supersession chain longer than this is a data-entry loop, not a part
     * history.
     */
    private const MAX_SUPERSESSION_DEPTH = 10;

    public function __construct(private readonly StockService $stock) {}

    public function enabled(): bool
    {
        return app(BusinessProfile::class)->has(BusinessCapability::Fitment);
    }

    // -- The finder --------------------------------------------------------

    /**
     * Parts that fit a vehicle, with what is on the shelf.
     *
     * @return Collection<int, array{item: MenuItem, fitment: ItemFitment, on_hand: float}>
     */
    public function partsFor(?string $modelId, ?int $year = null, ?string $engine = null): Collection
    {
        if (blank($modelId)) {
            return collect();
        }

        $fitments = ItemFitment::query()
            ->with(['item.menuCategory', 'vehicleModel.make'])
            ->where('vehicle_model_id', $modelId)
            ->coveringYear($year)
            ->when(filled($engine), fn ($query) => $query->where(fn ($q) => $q
                ->whereNull('engine')
                ->orWhere('engine', 'like', '%'.$engine.'%')))
            ->get()
            ->filter(fn (ItemFitment $fitment) => $fitment->item !== null);

        $onHand = $this->stock->onHandFor($fitments->pluck('item.id')->unique()->all());

        return $fitments
            ->map(fn (ItemFitment $fitment) => [
                'item' => $fitment->item,
                'fitment' => $fitment,
                'on_hand' => $onHand[$fitment->item->id] ?? 0.0,
            ])
            ->sortBy('item.name')
            ->values();
    }

    /**
     * Find a part by any number that has ever named it: our own SKU or barcode,
     * the manufacturer's, or an aftermarket brand's.
     *
     * Matching is done on the stripped form, because "0 986 424 815" and
     * "0986424815" are the same number and nobody types them the same way.
     *
     * @return Collection<int, MenuItem>
     */
    public function findByReference(string $reference): Collection
    {
        $needle = ItemReference::normalise($reference);

        if ($needle === '') {
            return collect();
        }

        $viaReference = ItemReference::query()
            ->get(['menu_item_id', 'reference'])
            ->filter(fn (ItemReference $row) => str_contains(ItemReference::normalise($row->reference), $needle))
            ->pluck('menu_item_id')
            ->unique();

        $viaCatalogue = MenuItem::query()
            ->where('kind', 'goods')
            ->where(fn ($query) => $query
                ->where('sku', 'like', '%'.trim($reference).'%')
                ->orWhere('barcode', 'like', '%'.trim($reference).'%'))
            ->pluck('id');

        $ids = $viaReference->merge($viaCatalogue)->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        return MenuItem::query()
            ->with(['menuCategory', 'references', 'supersededBy'])
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }

    /**
     * Follow a withdrawn part forward to the one now supplied in its place.
     *
     * A chain, not one hop: parts are replaced more than once over a model's
     * life, and the counter needs the end of it. Guarded against loops, which
     * bad data produces easily.
     *
     * @return Collection<int, MenuItem>
     */
    public function supersessionChain(MenuItem $item): Collection
    {
        $chain = collect();
        $seen = [$item->id];
        $current = $item;

        for ($depth = 0; $depth < self::MAX_SUPERSESSION_DEPTH; $depth++) {
            $next = $current->supersededBy;

            if (! $next instanceof MenuItem || in_array($next->id, $seen, true)) {
                break;
            }

            $chain->push($next);
            $seen[] = $next->id;
            $current = $next;
        }

        return $chain;
    }

    /**
     * Read a chassis number for what it can honestly say.
     *
     * The make comes from the WMI when one is registered; the year from position
     * ten. Model and engine are not attempted - those digits are
     * manufacturer-defined and reading them needs a licensed database.
     *
     * @return array{vin: string, valid: bool, wmi: ?string, year: ?int, checkDigit: bool, make: ?VehicleMake}
     */
    public function decodeVin(string $vin): array
    {
        $decoded = Vin::decode($vin);

        $decoded['make'] = $decoded['wmi'] === null
            ? null
            : VehicleMake::query()->where('wmi', $decoded['wmi'])->first();

        return $decoded;
    }

    // -- Editing -----------------------------------------------------------

    /**
     * Replace the vehicles a part fits.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function replaceFitments(MenuItem $item, array $rows): MenuItem
    {
        return DB::connection('tenant')->transaction(function () use ($item, $rows) {
            $item->fitments()->delete();

            foreach ($rows as $row) {
                if (blank($row['vehicleModelId'] ?? null)) {
                    continue;
                }

                $from = filled($row['yearFrom'] ?? null) ? (int) $row['yearFrom'] : null;
                $to = filled($row['yearTo'] ?? null) ? (int) $row['yearTo'] : null;

                // A range that runs backwards is a slip at the keyboard; taking
                // it literally would make the part fit nothing.
                if ($from !== null && $to !== null && $from > $to) {
                    [$from, $to] = [$to, $from];
                }

                $item->fitments()->create([
                    'vehicle_model_id' => $row['vehicleModelId'],
                    'year_from' => $from,
                    'year_to' => $to,
                    'engine' => filled($row['engine'] ?? null) ? trim((string) $row['engine']) : null,
                    'notes' => filled($row['notes'] ?? null) ? trim((string) $row['notes']) : null,
                ]);
            }

            return $item->fresh('fitments');
        });
    }

    /**
     * Replace the cross-references on a part.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function replaceReferences(MenuItem $item, array $rows): MenuItem
    {
        return DB::connection('tenant')->transaction(function () use ($item, $rows) {
            $item->references()->delete();

            $seen = [];

            foreach ($rows as $row) {
                $reference = trim((string) ($row['reference'] ?? ''));
                $key = ItemReference::normalise($reference);

                if ($key === '' || in_array($key, $seen, true)) {
                    continue;
                }

                $seen[] = $key;

                $item->references()->create([
                    'type' => ($row['type'] ?? 'oe') === 'aftermarket' ? 'aftermarket' : 'oe',
                    'reference' => $reference,
                    'brand' => filled($row['brand'] ?? null) ? trim((string) $row['brand']) : null,
                ]);
            }

            return $item->fresh('references');
        });
    }

    /**
     * Point a withdrawn part at its replacement.
     */
    public function supersede(MenuItem $item, ?string $replacementId): MenuItem
    {
        if ($replacementId === $item->id) {
            throw new RuntimeException(__('لا يمكن استبدال القطعة بنفسها.'));
        }

        if (filled($replacementId)) {
            $replacement = MenuItem::findOrFail($replacementId);

            // Following the replacement forward must not lead back here.
            if ($this->supersessionChain($replacement)->contains('id', $item->id)) {
                throw new RuntimeException(__('سلسلة الاستبدال ستعود إلى نفسها.'));
            }
        }

        $item->update(['superseded_by_item_id' => filled($replacementId) ? $replacementId : null]);

        return $item->fresh('supersededBy');
    }

    // -- Vehicles ----------------------------------------------------------

    /**
     * @return Collection<int, VehicleMake>
     */
    public function makes(): Collection
    {
        return VehicleMake::query()
            ->with(['models' => fn ($query) => $query->withCount('fitments')])
            ->withCount('models')
            ->orderBy('name')
            ->get();
    }

    public function createMake(array $data): VehicleMake
    {
        return VehicleMake::create([
            'name' => $data['name'],
            'wmi' => filled($data['wmi'] ?? null) ? strtoupper(trim((string) $data['wmi'])) : null,
            'active' => (bool) ($data['active'] ?? true),
        ]);
    }

    public function createModel(VehicleMake $make, array $data): VehicleModel
    {
        return $make->models()->create([
            'name' => $data['name'],
            'active' => (bool) ($data['active'] ?? true),
        ]);
    }

    /**
     * A model still named by a fitment cannot go: the parts pointing at it would
     * stop being able to say what they fit.
     */
    public function deleteModel(VehicleModel $model): void
    {
        if ($model->fitments()->exists()) {
            throw new RuntimeException(__('لا يمكن حذف موديل مرتبط بقطع — عطّله بدل ذلك.'));
        }

        $model->delete();
    }
}
