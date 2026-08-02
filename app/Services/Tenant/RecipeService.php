<?php

namespace App\Services\Tenant;

use App\Enums\BusinessCapability;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\RecipeComponent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bills of materials.
 *
 * Two jobs: work out what a finished item actually costs to make, and work out
 * what leaves the shelf when one is sold.
 *
 * Both need the same operation - flattening a recipe down to the things that are
 * really stocked. A sandwich is bread and filling; the bread is flour, water and
 * yeast. How far down to go is not a fixed depth: the explosion stops at
 * whatever is stocked, because a bakery that holds finished dough depletes the
 * dough, while a kitchen that mixes it per order depletes the flour.
 */
class RecipeService
{
    /**
     * A recipe that contains itself would recurse forever. The guard is a seen
     * set rather than a depth limit so the error names the actual loop.
     */
    private const MAX_DEPTH = 12;

    public function __construct(private readonly BusinessProfile $business) {}

    public function enabled(): bool
    {
        return $this->business->has(BusinessCapability::Recipes);
    }

    /**
     * Replace an item's recipe wholesale.
     *
     * @param  array<int, array{componentId: string, quantity: float|string, wastePercent?: float|string|null}>  $components
     */
    public function replace(MenuItem $item, array $components): MenuItem
    {
        return DB::connection('tenant')->transaction(function () use ($item, $components) {
            $item->recipeComponents()->delete();

            $position = 0;

            foreach ($components as $component) {
                $componentId = $component['componentId'] ?? null;

                if (blank($componentId) || $componentId === $item->id) {
                    // An item that contains itself is always a data-entry slip,
                    // and letting it through would poison every explosion.
                    continue;
                }

                $quantity = (float) ($component['quantity'] ?? 0);

                if ($quantity <= 0) {
                    continue;
                }

                $this->assertNoCycle($item, (string) $componentId);

                $item->recipeComponents()->create([
                    'component_item_id' => $componentId,
                    'quantity' => $quantity,
                    'waste_percent' => (float) ($component['wastePercent'] ?? 0),
                    'position' => $position++,
                ]);
            }

            return $item->fresh('recipeComponents');
        });
    }

    /**
     * Flatten a recipe into the quantities that actually leave the shelf.
     *
     * Stops descending at anything stocked, because that is the level the
     * business counts at. Quantities are gross of yield loss and multiply
     * through every level.
     *
     * @return Collection<int, array{item: MenuItem, quantity: float}>
     */
    public function explode(MenuItem $item, float $quantity = 1.0, array $seen = [], int $depth = 0): Collection
    {
        if ($depth >= self::MAX_DEPTH || in_array($item->id, $seen, true)) {
            return collect();
        }

        $seen[] = $item->id;
        $exploded = collect();

        $components = RecipeComponent::query()
            ->where('parent_item_id', $item->id)
            ->with('component')
            ->orderBy('position')
            ->get();

        foreach ($components as $component) {
            $child = $component->component;

            if (! $child instanceof MenuItem) {
                continue;
            }

            $needed = $component->grossQuantity() * $quantity;

            // Descend only through things the business does not count. Anything
            // stocked is where depletion stops - going further would deplete the
            // flour a second time after the dough already accounted for it.
            if (! $child->isStocked() && $child->hasRecipe()) {
                $exploded = $exploded->concat($this->explode($child, $needed, $seen, $depth + 1));

                continue;
            }

            $exploded->push(['item' => $child, 'quantity' => $needed]);
        }

        return $this->merge($exploded);
    }

    /**
     * What one unit costs to make, at the components' weighted-average costs.
     *
     * This is the theoretical cost that menu pricing is judged against; the
     * difference between it and reality is waste, over-portioning or theft.
     */
    public function theoreticalCost(MenuItem $item): float
    {
        return round(
            $this->explode($item)->sum(fn (array $row) => $row['quantity'] * (float) $row['item']->cost),
            3,
        );
    }

    /**
     * Margin of the selling price over the theoretical cost, as a percentage of
     * the net price. Null when either side is missing.
     */
    public function theoreticalMargin(MenuItem $item): ?float
    {
        $net = $item->netPrice();
        $cost = $this->theoreticalCost($item);

        if ($net <= 0 || $cost <= 0) {
            return null;
        }

        return round((($net - $cost) / $net) * 100, 1);
    }

    /**
     * Items that can legally be an ingredient of this one.
     *
     * Excludes itself and anything that already depends on it, so the picker
     * cannot be used to build a loop in the first place.
     *
     * @return Collection<int, MenuItem>
     */
    public function eligibleComponents(MenuItem $item): Collection
    {
        $blocked = $this->dependents($item);
        $blocked[] = $item->id;

        return MenuItem::query()
            ->whereNotIn('id', $blocked)
            ->where('kind', 'goods')
            ->orderBy('name')
            ->get();
    }

    /**
     * Every item that would be affected by a change to this one - the recipes it
     * feeds, and the recipes those feed.
     *
     * @return list<string>
     */
    public function dependents(MenuItem $item, array $seen = []): array
    {
        if (in_array($item->id, $seen, true)) {
            return $seen;
        }

        $parents = RecipeComponent::query()
            ->where('component_item_id', $item->id)
            ->pluck('parent_item_id');

        foreach ($parents as $parentId) {
            if (in_array($parentId, $seen, true)) {
                continue;
            }

            $seen[] = $parentId;

            $parent = MenuItem::find($parentId);

            if ($parent instanceof MenuItem) {
                $seen = $this->dependents($parent, $seen);
            }
        }

        return $seen;
    }

    /**
     * Refuse a component that would make the item an ingredient of itself,
     * however many levels away.
     */
    private function assertNoCycle(MenuItem $item, string $componentId): void
    {
        $component = MenuItem::find($componentId);

        if (! $component instanceof MenuItem) {
            return;
        }

        $reachable = $this->explode($component)->pluck('item.id')->all();

        // Also walk the unstocked intermediates, which explode() passes through
        // rather than returning.
        if (in_array($item->id, $reachable, true) || in_array($item->id, $this->intermediates($component), true)) {
            throw new RuntimeException(__('لا يمكن إضافة هذا المكوّن: سينشئ وصفة تحتوي نفسها.'));
        }
    }

    /**
     * Ids of the unstocked sub-recipes an explosion passes through.
     *
     * @return list<string>
     */
    private function intermediates(MenuItem $item, array $seen = [], int $depth = 0): array
    {
        if ($depth >= self::MAX_DEPTH || in_array($item->id, $seen, true)) {
            return $seen;
        }

        $seen[] = $item->id;

        foreach ($item->recipeComponents()->with('component')->get() as $component) {
            $child = $component->component;

            if ($child instanceof MenuItem) {
                $seen = $this->intermediates($child, $seen, $depth + 1);
            }
        }

        return $seen;
    }

    /**
     * Collapse an ingredient that appears more than once - cheese in the filling
     * and cheese on top is one draw on the shelf.
     *
     * @param  Collection<int, array{item: MenuItem, quantity: float}>  $rows
     * @return Collection<int, array{item: MenuItem, quantity: float}>
     */
    private function merge(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (array $row) => $row['item']->id)
            ->map(fn (Collection $group) => [
                'item' => $group->first()['item'],
                'quantity' => round($group->sum('quantity'), 4),
            ])
            ->values();
    }
}
