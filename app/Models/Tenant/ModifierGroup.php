<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'selection', 'is_required', 'min_select', 'max_select', 'position', 'active'])]
class ModifierGroup extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'active' => 'boolean',
            'min_select' => 'integer',
            'max_select' => 'integer',
            'position' => 'integer',
        ];
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(Modifier::class)->orderBy('position')->orderBy('name');
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_modifier_group');
    }

    public function isSingle(): bool
    {
        return $this->selection === 'single';
    }

    public function selectionLabel(): string
    {
        return $this->isSingle() ? __('اختيار واحد') : __('اختيار متعدد');
    }

    /**
     * Largest number of options the customer may take.
     *
     * A single-choice group is one by definition; a multiple-choice group is
     * unbounded unless a ceiling was set.
     */
    public function maxSelections(): ?int
    {
        return $this->isSingle() ? 1 : $this->max_select;
    }

    /**
     * Smallest number that must be taken. Required implies at least one, which
     * saves every caller from checking both fields.
     */
    public function minSelections(): int
    {
        return $this->is_required ? max(1, (int) $this->min_select) : (int) $this->min_select;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
