<?php

namespace App\Models\Tenant;

use App\Enums\UnitOfMeasure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'menu_category_id', 'parent_item_id', 'superseded_by_item_id',
    'name', 'icon', 'kind', 'price', 'core_charge', 'active',
    'barcode', 'sku', 'plu_code',
    'unit_code', 'purchase_unit_code', 'purchase_conversion',
    'cost', 'tax_rate', 'tax_included', 'tax_category', 'track_stock', 'reorder_point',
    'track_serials', 'warranty_months', 'service_minutes', 'allergens_reviewed_at',
    'is_prescription', 'control_schedule', 'atc_code',
    'active_ingredient', 'strength', 'dosage_form', 'cold_chain',
])]
class MenuItem extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:3',
            'cost' => 'decimal:3',
            'core_charge' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'purchase_conversion' => 'decimal:3',
            'reorder_point' => 'decimal:3',
            'active' => 'boolean',
            'tax_included' => 'boolean',
            'track_stock' => 'boolean',
            'track_serials' => 'boolean',
            'warranty_months' => 'integer',
            'service_minutes' => 'integer',
            'is_prescription' => 'boolean',
            'cold_chain' => 'boolean',
            'allergens_reviewed_at' => 'datetime',
        ];
    }

    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class);
    }

    /**
     * The style this variant belongs to, if it is one.
     */
    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_item_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_item_id');
    }

    /**
     * The axes this style varies on - size, colour - as constant lists.
     */
    public function variantAxes(): BelongsToMany
    {
        return $this->belongsToMany(ConstantList::class, 'menu_item_variant_axes', 'parent_item_id', 'constant_list_id')
            ->withPivot('position')
            ->orderBy('menu_item_variant_axes.position');
    }

    /**
     * Where this variant sits on each axis.
     */
    public function variantValues(): HasMany
    {
        return $this->hasMany(MenuItemVariantValue::class);
    }

    /**
     * A style, never sold itself. Derived from having children rather than
     * stored, so the two can never disagree.
     */
    public function isVariantParent(): bool
    {
        return $this->relationLoaded('variants')
            ? $this->variants->isNotEmpty()
            : $this->variants()->exists();
    }

    public function isVariant(): bool
    {
        return $this->parent_item_id !== null;
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(TransferLineItem::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class);
    }

    public function fitments(): HasMany
    {
        return $this->hasMany(ItemFitment::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(ItemReference::class);
    }

    /**
     * The part that replaced this one, if it was withdrawn.
     */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_item_id');
    }

    public function supersedes(): HasMany
    {
        return $this->hasMany(self::class, 'superseded_by_item_id');
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_by_item_id !== null;
    }

    /**
     * What this item is made of.
     */
    public function recipeComponents(): HasMany
    {
        return $this->hasMany(RecipeComponent::class, 'parent_item_id')->orderBy('position');
    }

    /**
     * Recipes this item appears in as an ingredient. Guards deletion and answers
     * "what breaks if this ingredient goes away".
     */
    public function usedInRecipes(): HasMany
    {
        return $this->hasMany(RecipeComponent::class, 'component_item_id');
    }

    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'menu_item_modifier_group')
            ->withPivot('position')
            ->orderBy('menu_item_modifier_group.position');
    }

    public function allergenDeclarations(): HasMany
    {
        return $this->hasMany(MenuItemAllergen::class);
    }

    /**
     * Whether anyone has ever looked at this item's allergens.
     *
     * Distinct from having none declared: "we checked, there are none" and
     * "nobody has checked" are opposite answers to an inspector.
     */
    public function allergensReviewed(): bool
    {
        return $this->allergens_reviewed_at !== null;
    }

    public function hasRecipe(): bool
    {
        return $this->recipeComponents()->exists();
    }

    /**
     * Whether selling one of these should consume its components rather than
     * itself.
     *
     * A restaurant assembles to order, so the dish is never stocked and its
     * ingredients are. A bakery produces to stock, so the loaf IS stocked and
     * its flour was already consumed by the production run - depleting the
     * recipe again at the till would double-count.
     */
    public function assemblesToOrder(): bool
    {
        return ! $this->isService() && ! $this->track_stock && $this->hasRecipe();
    }

    public function purchaseLines(): HasMany
    {
        return $this->hasMany(PurchaseLineItem::class);
    }

    public function unit(): UnitOfMeasure
    {
        return UnitOfMeasure::tryFrom((string) $this->unit_code) ?? UnitOfMeasure::Piece;
    }

    public function purchaseUnit(): UnitOfMeasure
    {
        return UnitOfMeasure::tryFrom((string) $this->purchase_unit_code) ?? $this->unit();
    }

    public function isService(): bool
    {
        return $this->kind === 'service';
    }

    /**
     * Whether the stock ledger should follow this item at all. Services have
     * nothing to count, and a tenant can opt an individual item out.
     */
    public function isStocked(): bool
    {
        return ! $this->isService() && (bool) $this->track_stock;
    }

    /**
     * How many sales units come in one purchase unit. One when the item is bought
     * and sold the same way, which is the common case.
     */
    public function conversionFactor(): float
    {
        $factor = (float) $this->purchase_conversion;

        return $factor > 0 ? $factor : 1.0;
    }

    /**
     * Selling price net of tax. Prices are usually entered tax-inclusive in this
     * market, so the net figure has to be derived rather than stored.
     */
    public function netPrice(): float
    {
        $price = (float) $this->price;
        $rate = (float) $this->tax_rate;

        if (! $this->tax_included || $rate <= 0) {
            return $price;
        }

        return $price / (1 + ($rate / 100));
    }

    public function taxAmount(): float
    {
        return round((float) $this->price - $this->netPrice(), 2);
    }

    /**
     * Gross margin against the weighted-average cost, as a percentage of the net
     * selling price. Null when there is no cost to compare against yet.
     */
    public function marginPercent(): ?float
    {
        $net = $this->netPrice();
        $cost = (float) $this->cost;

        if ($net <= 0 || $cost <= 0) {
            return null;
        }

        return round((($net - $cost) / $net) * 100, 1);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Items the till may ring up.
     *
     * A style is a container, not a product: nobody buys "the shirt", they buy
     * the medium red one. Excluding parents here is what keeps an unsellable row
     * off the order screen.
     */
    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('active', true)->whereDoesntHave('variants');
    }

    public function scopeStocked(Builder $query): Builder
    {
        return $query->where('kind', 'goods')
            ->where('track_stock', true)
            ->whereDoesntHave('variants');
    }
}
