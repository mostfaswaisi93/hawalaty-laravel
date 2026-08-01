<?php

namespace App\Models\Tenant;

use App\Enums\ChangeMethod;
use App\Enums\PaymentMethod;
use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'customer', 'customer_id', 'status', 'amount', 'discount', 'item_name', 'notes', 'payment_method',
    'has_change', 'change_method', 'change_amount',
    'sender_name', 'sender_phone', 'transferred_amount', 'reference_number',
    'confirmation_token', 'confirmed_at', 'confirmation_action', 'created_by',
    'account_id', 'change_account_id',
])]
class Transfer extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'has_change' => 'boolean',
            'change_method' => ChangeMethod::class,
            'change_amount' => 'decimal:2',
            'transferred_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
        ];
    }

    public function confirmationDecided(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class)->withTrashed();
    }

    public function changeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'change_account_id')->withTrashed();
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(TransferLineItem::class);
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by');
    }
}
