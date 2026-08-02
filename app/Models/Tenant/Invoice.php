<?php

namespace App\Models\Tenant;

use App\Enums\InvoiceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An issued invoice. Immutable by contract.
 *
 * Nothing in the application updates one after it is written: the hash chain
 * exists precisely so that an edit is detectable, and offering an edit button
 * would be offering to break it.
 */
#[Fillable([
    'transfer_id', 'sale_return_id', 'series', 'sequence', 'number', 'type',
    'issued_at', 'currency_code',
    'seller_name', 'seller_tax_number', 'seller_address',
    'buyer_name', 'buyer_tax_number', 'buyer_address',
    'line_extension_amount', 'allowance_total', 'tax_exclusive_amount',
    'tax_amount', 'tax_inclusive_amount', 'payable_amount',
    'previous_hash', 'hash', 'qr_payload', 'xml', 'team_member_id',
])]
class Invoice extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'issued_at' => 'datetime',
            'sequence' => 'integer',
            'line_extension_amount' => 'decimal:2',
            'allowance_total' => 'decimal:2',
            'tax_exclusive_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'tax_inclusive_amount' => 'decimal:2',
            'payable_amount' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('position');
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    public function isCreditNote(): bool
    {
        return $this->type === InvoiceType::CreditNote;
    }

    /**
     * Tax grouped the way EN 16931 wants it reported: one subtotal per category
     * and rate, not one per line.
     *
     * @return \Illuminate\Support\Collection<int, array{category: string, rate: float, taxable: float, tax: float}>
     */
    public function taxBreakdown(): \Illuminate\Support\Collection
    {
        return $this->lines
            ->groupBy(fn (InvoiceLine $line) => $line->tax_category.'|'.$line->tax_rate)
            ->map(fn ($group) => [
                'category' => $group->first()->tax_category,
                'rate' => (float) $group->first()->tax_rate,
                'taxable' => round((float) $group->sum('line_extension_amount'), 2),
                'tax' => round((float) $group->sum('tax_amount'), 2),
            ])
            ->values();
    }

    /**
     * Whether the totals still add up. Cheap to check and worth showing: a
     * document whose arithmetic has drifted would be rejected on submission.
     */
    public function totalsBalance(): bool
    {
        $exclusive = round((float) $this->line_extension_amount - (float) $this->allowance_total, 2);

        return abs($exclusive - (float) $this->tax_exclusive_amount) < 0.005
            && abs($exclusive + (float) $this->tax_amount - (float) $this->tax_inclusive_amount) < 0.005;
    }

    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('series')->orderBy('sequence');
    }
}
