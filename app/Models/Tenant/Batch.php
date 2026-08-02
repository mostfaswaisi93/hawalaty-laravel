<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['menu_item_id', 'batch_number', 'expires_at', 'supplier_id', 'received_at', 'notes'])]
class Batch extends Model
{
    use HasFactory, HasUlids;

    /**
     * How many days ahead counts as "expiring soon".
     *
     * A default, not a rule: the window that matters differs by trade, so the
     * reports take it as a parameter and only fall back to this.
     */
    public const SOON_DAYS = 30;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'received_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Quantity still on the shelf from this lot. Derived, like every balance.
     */
    public function onHand(?string $locationId = null): float
    {
        return (float) $this->movements()
            ->atLocation($locationId)
            ->sum('quantity');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function expiresWithin(int $days = self::SOON_DAYS): bool
    {
        return $this->expires_at !== null
            && ! $this->isExpired()
            && $this->expires_at->lessThanOrEqualTo(now()->addDays($days));
    }

    /**
     * Days left before expiry. Negative once it has passed, null when the lot
     * has no expiry at all - which is not the same as "plenty of time".
     */
    public function daysLeft(): ?int
    {
        return $this->expires_at === null
            ? null
            : (int) now()->startOfDay()->diffInDays($this->expires_at->startOfDay(), false);
    }

    public function status(int $soonDays = self::SOON_DAYS): string
    {
        return match (true) {
            $this->isExpired() => 'expired',
            $this->expiresWithin($soonDays) => 'soon',
            default => 'fresh',
        };
    }

    public function statusLabel(int $soonDays = self::SOON_DAYS): string
    {
        return match ($this->status($soonDays)) {
            'expired' => __('منتهي'),
            'soon' => __('قارب على الانتهاء'),
            default => __('سليم'),
        };
    }

    /**
     * Lots that may still be dispensed: not expired.
     *
     * Kept as a scope because it guards two different things - the FEFO picker
     * and the expiry reports - and they must agree on what "sellable" means.
     */
    public function scopeSellable(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', now()->toDateString());
        });
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')->whereDate('expires_at', '<', now()->toDateString());
    }

    public function scopeExpiringWithin(Builder $query, int $days = self::SOON_DAYS): Builder
    {
        return $query->whereNotNull('expires_at')
            ->whereDate('expires_at', '>=', now()->toDateString())
            ->whereDate('expires_at', '<=', now()->addDays($days)->toDateString());
    }

    /**
     * First-expiry-first-out ordering.
     *
     * Lots without an expiry sort last: a dated lot always has more urgency than
     * an undated one, and MySQL would otherwise sort NULL to the front.
     */
    public function scopeFefo(Builder $query): Builder
    {
        return $query
            ->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at')
            ->orderBy('received_at');
    }
}
