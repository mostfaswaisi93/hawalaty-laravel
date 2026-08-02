<?php

namespace App\Services\Tenant;

use App\Enums\BusinessCapability;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Modifier;
use App\Models\Tenant\ModifierGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sale-time options: no onion, extra cheese, large.
 *
 * The rule that matters here is that nothing the browser sends about price is
 * believed. The till posts which options were chosen; what they cost is looked
 * up again on the server, because a price that arrives from the client is a
 * price the customer can edit.
 */
class ModifierService
{
    public function __construct(private readonly BusinessProfile $business) {}

    public function enabled(): bool
    {
        return $this->business->has(BusinessCapability::Modifiers);
    }

    /**
     * @return Collection<int, ModifierGroup>
     */
    public function groups(bool $activeOnly = false): Collection
    {
        return ModifierGroup::query()
            ->with(['modifiers' => fn ($query) => $activeOnly ? $query->where('active', true) : $query])
            ->withCount('items')
            ->when($activeOnly, fn ($query) => $query->active())
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }

    public function createGroup(array $data): ModifierGroup
    {
        return ModifierGroup::create($this->groupAttributes($data));
    }

    public function updateGroup(ModifierGroup $group, array $data): ModifierGroup
    {
        $group->update($this->groupAttributes($data));

        return $group;
    }

    public function createModifier(ModifierGroup $group, array $data): Modifier
    {
        return $group->modifiers()->create($this->modifierAttributes($data));
    }

    public function updateModifier(Modifier $modifier, array $data): Modifier
    {
        $modifier->update($this->modifierAttributes($data));

        return $modifier;
    }

    /**
     * Attach the groups an item offers, replacing whatever it had.
     *
     * @param  list<string>  $groupIds
     */
    public function syncItemGroups(MenuItem $item, array $groupIds): void
    {
        DB::connection('tenant')->transaction(function () use ($item, $groupIds) {
            $item->modifierGroups()->sync(
                collect($groupIds)
                    ->values()
                    ->mapWithKeys(fn (string $id, int $index) => [$id => ['position' => $index]])
                    ->all(),
            );
        });
    }

    /**
     * Turn the ids the till posted into priced, validated selections.
     *
     * Rejects anything that is not on offer for that item, and enforces the
     * group's own rules - a required size cannot be skipped, and a single-choice
     * group cannot take two.
     *
     * @param  list<string>  $modifierIds
     * @return Collection<int, Modifier>
     */
    public function resolveFor(MenuItem $item, array $modifierIds): Collection
    {
        if (! $this->enabled()) {
            return collect();
        }

        $offered = Modifier::query()
            ->active()
            ->whereIn('modifier_group_id', $item->modifierGroups()->pluck('modifier_groups.id'))
            ->get()
            ->keyBy('id');

        $chosen = collect($modifierIds)
            ->unique()
            ->map(fn ($id) => $offered->get($id))
            ->filter()
            ->values();

        $this->assertGroupRules($item, $chosen);

        return $chosen;
    }

    /**
     * What the chosen options add to the unit price. Signed, so a smaller size
     * subtracts.
     *
     * @param  Collection<int, Modifier>  $modifiers
     */
    public function priceDelta(Collection $modifiers): float
    {
        return round((float) $modifiers->sum(fn (Modifier $modifier) => (float) $modifier->price_delta), 3);
    }

    /**
     * @param  Collection<int, Modifier>  $chosen
     */
    private function assertGroupRules(MenuItem $item, Collection $chosen): void
    {
        $counts = $chosen->groupBy('modifier_group_id')->map->count();

        foreach ($item->modifierGroups as $group) {
            $taken = (int) ($counts[$group->id] ?? 0);
            $min = $group->minSelections();
            $max = $group->maxSelections();

            if ($taken < $min) {
                throw new RuntimeException(__('اختر :count على الأقل من :group.', [
                    'count' => $min,
                    'group' => $group->name,
                ]));
            }

            if ($max !== null && $taken > $max) {
                throw new RuntimeException(__('لا يمكن اختيار أكثر من :count من :group.', [
                    'count' => $max,
                    'group' => $group->name,
                ]));
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function groupAttributes(array $data): array
    {
        $selection = ($data['selection'] ?? 'single') === 'multiple' ? 'multiple' : 'single';

        return [
            'name' => $data['name'],
            'selection' => $selection,
            'is_required' => (bool) ($data['isRequired'] ?? false),
            'min_select' => (int) ($data['minSelect'] ?? 0),
            // A single-choice group has no meaningful ceiling beyond one, and
            // storing a contradictory number would only mislead a later reader.
            'max_select' => $selection === 'single' ? 1 : ($data['maxSelect'] ?? null),
            'position' => (int) ($data['position'] ?? 0),
            'active' => (bool) ($data['active'] ?? true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function modifierAttributes(array $data): array
    {
        $componentId = $data['componentItemId'] ?? null;

        return [
            'name' => $data['name'],
            'price_delta' => (float) ($data['priceDelta'] ?? 0),
            'component_item_id' => filled($componentId) ? $componentId : null,
            'component_quantity' => filled($componentId) ? (float) ($data['componentQuantity'] ?? 0) : null,
            'is_default' => (bool) ($data['isDefault'] ?? false),
            'position' => (int) ($data['position'] ?? 0),
            'active' => (bool) ($data['active'] ?? true),
        ];
    }
}
