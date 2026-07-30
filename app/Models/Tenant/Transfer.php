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
    'customer', 'customer_id', 'status', 'amount', 'item_name', 'payment_method',
    'has_change', 'change_method', 'change_bank_id', 'change_amount',
    'bank_id', 'sender_name', 'sender_phone', 'transferred_amount',
    'confirmation_token', 'confirmed_at', 'confirmation_action', 'created_by',
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
            'payment_method' => PaymentMethod::class,
            'has_change' => 'boolean',
            'change_method' => ChangeMethod::class,
            'change_amount' => 'decimal:2',
            'transferred_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * True once the bank account owner has responded to the confirmation link,
     * either way. Drives the link's idempotency: a decided order shows its
     * outcome instead of the confirm/reject buttons.
     */
    public function confirmationDecided(): bool
    {
        return $this->confirmed_at !== null;
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

    // The team member who raised the order. Notified - and only them - when the
    // bank account owner confirms or rejects the transfer via the public link.
    public function creator(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by');
    }
}
