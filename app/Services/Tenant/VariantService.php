<?php

namespace App\Services\Tenant;

use App\Enums\BusinessCapability;
use App\Models\Tenant\ConstantList;
use App\Models\Tenant\ConstantListItem;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\MenuItemVariantValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Size x colour matrices.
 *
 * A variant is a menu_item with a parent, which is what lets the stock ledger,
 * purchases, sales and batches all work on it unchanged. The parent is a style:
 * it holds the axes and the shared attributes, and is never sold.
 *
 * Generating is additive by design. A shop that adds a colour in March must not
 * lose the stock history of the twenty variants it has been selling since
 * January, so a run creates what is missing and leaves the rest alone. Removing
 * a variant is a separate, deliberate act - and refused outright once it has
 * moved stock.
 */
class VariantService
{
    /**
     * Two axes is the normal case, three the outer edge. Beyond that the
     * cartesian product stops being a grid a human can work with, and the count
     * below is the real guard anyway.
     */
    private const MAX_AXES = 3;

    /**
     * A shop that asks for eight hundred variants in one click has made a
     * mistake with the value pickers, not a decision.
     */
    private const MAX_COMBINATIONS = 400;

    public function __construct(private readonly StockService $stock) {}

    public function enabled(): bool
    {
        return app(BusinessProfile::class)->has(BusinessCapability::Variants);
    }

    /**
     * Constant lists that can serve as axes - anything with values in it.
     *
     * @return Collection<int, ConstantList>
     */
    public function availableAxes(): Collection
    {
        return ConstantList::query()
            ->has('items')
            ->with(['items' => fn ($query) => $query->orderBy('position')])
            ->orderBy('title')
            ->get();
    }

    /**
     * Build the missing combinations for a style.
     *
     * @param  array<string, list<string>>  $selection  constant_list_id => list of constant_list_item_id
     * @return array{created: int, existing: int}
     */
    public function generate(MenuItem $parent, array $selection): array
    {
        $selection = array_filter($selection, fn ($values) => is_array($values) && $values !== []);

        if ($selection === []) {
            throw new RuntimeException(__('اختر قيمة واحدة على الأقل من كل محور.'));
        }

        if (count($selection) > self::MAX_AXES) {
            throw new RuntimeException(__('الحد الأقصى :count محاور للتنويعة الواحدة.', ['count' => self::MAX_AXES]));
        }

        if ($parent->isVariant()) {
            throw new RuntimeException(__('لا يمكن اشتقاق تنويعات من تنويعة.'));
        }

        $combinations = $this->combinations($selection);

        if (count($combinations) > self::MAX_COMBINATIONS) {
            throw new RuntimeException(__('عدد التنويعات :count يتجاوز الحد المسموح :max.', [
                'count' => count($combinations),
                'max' => self::MAX_COMBINATIONS,
            ]));
        }

        return DB::connection('tenant')->transaction(function () use ($parent, $selection, $combinations) {
            // Variants copy the style's shared attributes, so the style has to
            // be holding all of them. A model built without them would hand down
            // nulls into columns the schema fills with defaults.
            $parent->refresh();

            $this->syncAxes($parent, array_keys($selection));

            $values = ConstantListItem::query()
                ->whereIn('id', collect($combinations)->flatten()->unique()->all())
                ->get()
                ->keyBy('id');

            $existingKeys = $this->fingerprints($parent);

            $created = 0;
            $existing = 0;

            foreach ($combinations as $combination) {
                if (in_array($this->fingerprint($combination), $existingKeys, true)) {
                    $existing++;

                    continue;
                }

                $this->createVariant($parent, $combination, $values);
                $created++;
            }

            // A style is not itself stocked or sold; whatever it was before it
            // had children, it is a container now.
            $parent->update(['track_stock' => false]);

            return ['created' => $created, 'existing' => $existing];
        });
    }

    /**
     * Remove a variant, but only while it is still hypothetical.
     *
     * Once stock has moved against it the row is part of the ledger's history,
     * and deleting it would orphan movements that a valuation already counted.
     * Deactivating is the answer then.
     */
    public function remove(MenuItem $variant): void
    {
        if (! $variant->isVariant()) {
            throw new RuntimeException(__('هذا الصنف ليس تنويعة.'));
        }

        if ($variant->movements()->exists()) {
            throw new RuntimeException(__('لا يمكن حذف تنويعة لها حركات مخزنية — عطّلها بدل ذلك.'));
        }

        DB::connection('tenant')->transaction(function () use ($variant) {
            $variant->variantValues()->delete();
            $variant->delete();
        });
    }

    /**
     * The matrix as a grid: rows are the first axis, columns the second.
     *
     * Twenty variants as twenty rows is a list nobody reads. As a five-by-four
     * grid of numbers it is a shop floor someone can act on at a glance.
     *
     * @return array{
     *     rows: list<array{id: string, label: string}>,
     *     columns: list<array{id: string, label: string}>,
     *     cells: array<string, array{variant: MenuItem, on_hand: float}>,
     *     loose: Collection<int, array{variant: MenuItem, on_hand: float}>,
     * }
     */
    public function grid(MenuItem $parent, ?string $locationId = null): array
    {
        $axes = $parent->variantAxes;
        $variants = $parent->variants()->with('variantValues')->orderBy('name')->get();
        $onHand = $this->stock->onHandFor($variants->pluck('id')->all(), $locationId);

        $rowAxis = $axes->get(0);
        $columnAxis = $axes->get(1);

        $labels = ConstantListItem::query()
            ->whereIn('constant_list_id', $axes->pluck('id'))
            ->orderBy('position')
            ->get();

        $rows = $this->axisEntries($labels, $rowAxis?->id, $variants);
        $columns = $this->axisEntries($labels, $columnAxis?->id, $variants);

        $cells = [];
        $loose = collect();

        foreach ($variants as $variant) {
            $coordinates = $variant->variantValues->keyBy('constant_list_id');
            $row = $rowAxis ? $coordinates->get($rowAxis->id)?->constant_list_item_id : null;
            $column = $columnAxis ? $coordinates->get($columnAxis->id)?->constant_list_item_id : null;

            $entry = ['variant' => $variant, 'on_hand' => $onHand[$variant->id] ?? 0.0];

            // A variant on a third axis has no square on a two-dimensional grid.
            // Listing it separately beats silently hiding it.
            if ($rowAxis && $row === null || $axes->count() > 2) {
                $loose->push($entry);

                continue;
            }

            $cells[$this->cellKey($row, $column)] = $entry;
        }

        return ['rows' => $rows, 'columns' => $columns, 'cells' => $cells, 'loose' => $loose];
    }

    public function cellKey(?string $rowId, ?string $columnId): string
    {
        return ($rowId ?? '-').'|'.($columnId ?? '-');
    }

    /**
     * Total stock across a style's variants, for the listing.
     *
     * @param  Collection<int, MenuItem>  $parents
     * @return Collection<string, float>
     */
    public function stockByParent(Collection $parents, ?string $locationId = null): Collection
    {
        $variantIds = $parents->flatMap(fn (MenuItem $parent) => $parent->variants->pluck('id'))->all();
        $onHand = $this->stock->onHandFor($variantIds, $locationId);

        return $parents->mapWithKeys(fn (MenuItem $parent) => [
            $parent->id => $parent->variants->sum(fn (MenuItem $variant) => $onHand[$variant->id] ?? 0.0),
        ]);
    }

    /**
     * Only the axes actually used, in the order given.
     *
     * @param  list<string>  $axisIds
     */
    private function syncAxes(MenuItem $parent, array $axisIds): void
    {
        $parent->variantAxes()->sync(
            collect($axisIds)
                ->values()
                ->mapWithKeys(fn (string $id, int $index) => [$id => ['position' => $index]])
                ->all(),
        );
    }

    /**
     * Cartesian product of the chosen values, preserving axis order.
     *
     * Each returned entry maps an axis id to the value id chosen on it.
     *
     * @param  array<string, list<string>>  $selection
     * @return list<array<string, string>>
     */
    private function combinations(array $selection): array
    {
        $combinations = [[]];

        foreach ($selection as $axisId => $valueIds) {
            $expanded = [];

            foreach ($combinations as $combination) {
                foreach (array_unique($valueIds) as $valueId) {
                    $expanded[] = $combination + [$axisId => $valueId];
                }
            }

            $combinations = $expanded;
        }

        return $combinations;
    }

    /**
     * @param  array<string, string>  $combination
     */
    private function fingerprint(array $combination): string
    {
        $values = array_values($combination);
        sort($values);

        return implode('|', $values);
    }

    /**
     * @return list<string>
     */
    private function fingerprints(MenuItem $parent): array
    {
        return $parent->variants()
            ->with('variantValues')
            ->get()
            ->map(fn (MenuItem $variant) => $this->fingerprint(
                $variant->variantValues->pluck('constant_list_item_id', 'constant_list_id')->all(),
            ))
            ->all();
    }

    /**
     * @param  array<string, string>  $combination
     * @param  Collection<string, ConstantListItem>  $values
     */
    private function createVariant(MenuItem $parent, array $combination, Collection $values): MenuItem
    {
        $suffix = collect($combination)
            ->map(fn (string $valueId) => $values->get($valueId)?->value)
            ->filter()
            ->implode(' / ');

        $variant = MenuItem::create([
            'menu_category_id' => $parent->menu_category_id,
            'parent_item_id' => $parent->id,
            'name' => trim("{$parent->name} - {$suffix}"),
            'icon' => $parent->icon,
            'kind' => $parent->kind,

            // Shared attributes come down from the style; the identifiers that
            // must be unique per variant deliberately do not.
            'price' => $parent->price,
            'cost' => $parent->cost,
            'unit_code' => $parent->unit_code,
            'purchase_unit_code' => $parent->purchase_unit_code,
            'purchase_conversion' => $parent->purchase_conversion,
            'tax_rate' => $parent->tax_rate,
            'tax_included' => $parent->tax_included,
            'reorder_point' => $parent->reorder_point,

            'track_stock' => true,
            'active' => true,
        ]);

        foreach ($combination as $axisId => $valueId) {
            MenuItemVariantValue::create([
                'menu_item_id' => $variant->id,
                'constant_list_id' => $axisId,
                'constant_list_item_id' => $valueId,
            ]);
        }

        return $variant;
    }

    /**
     * Axis values that at least one variant actually uses, so the grid has no
     * empty rows for sizes the shop never made.
     *
     * @param  Collection<int, ConstantListItem>  $labels
     * @param  Collection<int, MenuItem>  $variants
     * @return list<array{id: string, label: string}>
     */
    private function axisEntries(Collection $labels, ?string $axisId, Collection $variants): array
    {
        if ($axisId === null) {
            return [];
        }

        $used = $variants
            ->flatMap(fn (MenuItem $variant) => $variant->variantValues->where('constant_list_id', $axisId))
            ->pluck('constant_list_item_id')
            ->unique();

        return $labels
            ->where('constant_list_id', $axisId)
            ->filter(fn (ConstantListItem $item) => $used->contains($item->id))
            ->map(fn (ConstantListItem $item) => ['id' => $item->id, 'label' => $item->value])
            ->values()
            ->all();
    }
}
