<?php

namespace App\Models\Tenant;

use App\Enums\ChangeMethod;
use App\Enums\PaymentMethod;
use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'customer', 'customer_id', 'status', 'amount', 'item_name', 'payment_method',
    'has_change', 'change_method', 'change_bank_id', 'change_amount',
    'bank_id', 'sender_name', 'sender_phone', 'transferred_amount',
])]
class Transfer extends Model
{
    use HasUlids, SoftDeletes;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'has_change' => 'boolean',
            'change_method' => ChangeMethod::class,
            'change_amount' => 'decimal:2',
            'transferred_amount' => 'decimal:2',
        ];
    }

    // withTrashed(): a soft-deleted bank must still show its name on
    // historical transfers instead of silently going null.
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class)->withTrashed();
    }

    public function changeBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'change_bank_id')->withTrashed();
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(TransferLineItem::class);
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
