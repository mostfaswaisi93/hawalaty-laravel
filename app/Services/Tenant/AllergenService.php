<?php

namespace App\Services\Tenant;

use App\Enums\Allergen;
use App\Enums\AllergenPresence;
use App\Enums\BusinessCapability;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\MenuItemAllergen;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Allergen declaration and inheritance.
 *
 * EU Regulation 1169/2011 requires the fourteen listed allergens to be declared
 * on food sold to the public. The hard part in a kitchen is not the list, it is
 * keeping it true: a dish declared by hand goes stale the first time someone
 * changes its recipe.
 *
 * So declarations are made against ingredients - flour is gluten, once - and
 * dishes inherit through their recipe. Change the recipe and the label follows.
 */
class AllergenService
{
    public function __construct(private readonly RecipeService $recipes) {}

    public function enabled(): bool
    {
        return app(BusinessProfile::class)->has(BusinessCapability::Allergens);
    }

    /**
     * Replace what an item declares about itself, and record that it was looked
     * at - an empty list from someone who checked is an answer, not a gap.
     *
     * @param  array<string, string>  $declarations  allergen value => presence value
     */
    public function declare(MenuItem $item, array $declarations): MenuItem
    {
        return DB::connection('tenant')->transaction(function () use ($item, $declarations) {
            $item->allergenDeclarations()->delete();

            foreach ($declarations as $allergen => $presence) {
                $case = Allergen::tryFrom((string) $allergen);

                if (! $case instanceof Allergen) {
                    continue;
                }

                MenuItemAllergen::create([
                    'menu_item_id' => $item->id,
                    'allergen' => $case,
                    'presence' => AllergenPresence::tryFrom((string) $presence) ?? AllergenPresence::Contains,
                ]);
            }

            $item->update(['allergens_reviewed_at' => now()]);

            return $item->fresh('allergenDeclarations');
        });
    }

    /**
     * What the item itself declares, ignoring its recipe.
     *
     * @return Collection<string, AllergenPresence>
     */
    public function declared(MenuItem $item): Collection
    {
        return $item->allergenDeclarations
            ->mapWithKeys(fn (MenuItemAllergen $row) => [$row->allergen->value => $row->presence]);
    }

    /**
     * What the item must be labelled with: its own declarations plus everything
     * its recipe reaches, at any depth.
     *
     * Where the same allergen arrives twice, the stronger claim wins - an
     * ingredient that definitely contains milk is not softened to "may contain"
     * because another ingredient only might.
     *
     * @return Collection<string, AllergenPresence>
     */
    public function effective(MenuItem $item): Collection
    {
        $effective = $this->declared($item);

        foreach ($this->recipes->allComponents($item) as $component) {
            foreach ($this->declared($component) as $allergen => $presence) {
                $existing = $effective->get($allergen);

                if (! $existing instanceof AllergenPresence || $presence->outranks($existing)) {
                    $effective[$allergen] = $presence;
                }
            }
        }

        return $effective;
    }

    /**
     * The same, resolved to enum cases and ordered like the regulation's list so
     * two items always read in the same order.
     *
     * @return Collection<int, array{allergen: Allergen, presence: AllergenPresence, inherited: bool}>
     */
    public function labelFor(MenuItem $item): Collection
    {
        return $this->order($this->effective($item), $this->declared($item));
    }

    /**
     * Labels for many items at once, keyed by item id.
     *
     * The per-item path walks the recipe graph with a query at every level,
     * which is fine for one dish and ruinous for a menu screen. This pulls the
     * whole graph and every declaration in two queries and resolves in memory.
     *
     * @param  Collection<int, MenuItem>  $items
     * @return Collection<string, Collection<int, array{allergen: Allergen, presence: AllergenPresence, inherited: bool}>>
     */
    public function labelsFor(Collection $items): Collection
    {
        if ($items->isEmpty()) {
            return collect();
        }

        $declarations = MenuItemAllergen::query()
            ->get()
            ->groupBy('menu_item_id')
            ->map(fn (Collection $rows) => $rows->mapWithKeys(
                fn (MenuItemAllergen $row) => [$row->allergen->value => $row->presence],
            ));

        $edges = DB::connection('tenant')
            ->table('recipe_components')
            ->select('parent_item_id', 'component_item_id')
            ->get()
            ->groupBy('parent_item_id')
            ->map(fn ($rows) => collect($rows)->pluck('component_item_id')->all());

        return $items->mapWithKeys(function (MenuItem $item) use ($declarations, $edges) {
            $own = $declarations->get($item->id, collect());
            $effective = collect($own);

            foreach ($this->reach($item->id, $edges) as $componentId) {
                foreach ($declarations->get($componentId, collect()) as $allergen => $presence) {
                    $existing = $effective->get($allergen);

                    if (! $existing instanceof AllergenPresence || $presence->outranks($existing)) {
                        $effective[$allergen] = $presence;
                    }
                }
            }

            return [$item->id => $this->order($effective, $own)];
        });
    }

    /**
     * Every item reachable through the recipe graph, breadth first with a seen
     * set so a loop cannot spin.
     *
     * @param  Collection<string, list<string>>  $edges
     * @return list<string>
     */
    private function reach(string $rootId, Collection $edges): array
    {
        $seen = [];
        $queue = $edges->get($rootId, []);

        while ($queue !== []) {
            $id = array_shift($queue);

            if (in_array($id, $seen, true) || $id === $rootId) {
                continue;
            }

            $seen[] = $id;

            foreach ($edges->get($id, []) as $child) {
                $queue[] = $child;
            }
        }

        return $seen;
    }

    /**
     * @param  Collection<string, AllergenPresence>  $effective
     * @param  Collection<string, AllergenPresence>  $own
     * @return Collection<int, array{allergen: Allergen, presence: AllergenPresence, inherited: bool}>
     */
    private function order(Collection $effective, Collection $own): Collection
    {
        return collect(Allergen::cases())
            ->filter(fn (Allergen $allergen) => $effective->has($allergen->value))
            ->map(fn (Allergen $allergen) => [
                'allergen' => $allergen,
                'presence' => $effective->get($allergen->value),
                'inherited' => ! $own->has($allergen->value),
            ])
            ->values();
    }

    /**
     * Items nobody has ever checked.
     *
     * The compliance list. An item with no allergens is fine; an item that was
     * never reviewed is the one that gets a kitchen fined - or worse, gets
     * somebody hurt.
     *
     * @return Collection<int, MenuItem>
     */
    public function unreviewed(): Collection
    {
        return MenuItem::query()
            ->where('kind', 'goods')
            ->whereNull('allergens_reviewed_at')
            ->orderBy('name')
            ->get();
    }

    /**
     * Dishes whose label rests entirely on ingredients that were never checked.
     *
     * Worse than an unreviewed dish, because this one looks answered: it shows a
     * confident empty label built on components nobody examined.
     *
     * @return Collection<int, MenuItem>
     */
    public function restingOnUnreviewedComponents(): Collection
    {
        return MenuItem::query()
            ->where('kind', 'goods')
            ->whereNotNull('allergens_reviewed_at')
            ->has('recipeComponents')
            ->with('allergenDeclarations')
            ->orderBy('name')
            ->get()
            ->filter(function (MenuItem $item) {
                $components = $this->recipes->allComponents($item);

                return $components->isNotEmpty()
                    && $components->every(fn (MenuItem $component) => ! $component->allergensReviewed());
            })
            ->values();
    }

    /**
     * How many items carry each allergen, for the overview.
     *
     * @return Collection<string, int>
     */
    public function spread(): Collection
    {
        return MenuItemAllergen::query()
            ->groupBy('allergen')
            ->selectRaw('allergen, COUNT(*) as total')
            ->pluck('total', 'allergen')
            ->map(fn ($total) => (int) $total);
    }
}
