<?php

namespace App\Models\Tenant;

use App\Enums\UnitOfMeasure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'menu_category_id', 'name', 'icon', 'kind', 'price', 'active',
    'barcode', 'sku', 'plu_code',
    'unit_code', 'purchase_unit_code', 'purchase_conversion',
    'cost', 'tax_rate', 'tax_included', 'track_stock', 'reorder_point',
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
            'tax_rate' => 'decimal:2',
            'purchase_conversion' => 'decimal:3',
            'reorder_point' => 'decimal:3',
            'active' => 'boolean',
            'tax_included' => 'boolean',
            'track_stock' => 'boolean',
        ];
    }

    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class);
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

    public function scopeStocked(Builder $query): Builder
    {
        return $query->where('kind', 'goods')->where('track_stock', true);
    }
}
