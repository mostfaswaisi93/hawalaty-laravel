<?php

namespace App\Models\Tenant;

use App\Enums\TaxCategory;
use App\Enums\UnitOfMeasure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id', 'menu_item_id', 'position', 'name', 'quantity', 'unit_code',
    'unit_price', 'line_extension_amount', 'tax_category', 'tax_rate', 'tax_amount',
])]
class InvoiceLine extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:3',
            'line_extension_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function category(): TaxCategory
    {
        return TaxCategory::tryFrom((string) $this->tax_category) ?? TaxCategory::Standard;
    }

    public function unit(): UnitOfMeasure
    {
        return UnitOfMeasure::tryFrom((string) $this->unit_code) ?? UnitOfMeasure::Piece;
    }

    public function grossAmount(): float
    {
        return round((float) $this->line_extension_amount + (float) $this->tax_amount, 2);
    }
}
