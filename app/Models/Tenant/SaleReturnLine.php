<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sale_return_id', 'transfer_line_item_id', 'menu_item_id', 'name',
    'quantity', 'unit_price', 'line_total', 'condition', 'serial_id', 'batch_id',
])]
class SaleReturnLine extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:3',
            'line_total' => 'decimal:2',
        ];
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function originalLine(): BelongsTo
    {
        return $this->belongsTo(TransferLineItem::class, 'transfer_line_item_id');
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(SerialNumber::class, 'serial_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function isResalable(): bool
    {
        return $this->condition === 'resalable';
    }

    public function conditionLabel(): string
    {
        return $this->isResalable() ? __('صالح للبيع') : __('تالف');
    }
}
