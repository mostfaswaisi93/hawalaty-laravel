<?php

namespace App\Models\Tenant;

use App\Enums\WorkOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference', 'customer_id', 'customer_name', 'customer_phone',
    'appointment_id', 'service_resource_id',
    'subject', 'registration', 'odometer', 'complaint', 'diagnosis', 'status',
    'parts_total', 'labour_total', 'total', 'transfer_id',
    'received_at', 'delivered_at', 'workmanship_warranty_days', 'team_member_id',
])]
class WorkOrder extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'status' => WorkOrderStatus::class,
            'parts_total' => 'decimal:2',
            'labour_total' => 'decimal:2',
            'total' => 'decimal:2',
            'received_at' => 'datetime',
            'delivered_at' => 'datetime',
            'odometer' => 'integer',
            'workmanship_warranty_days' => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WorkOrderLine::class)->orderBy('position');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(ServiceResource::class, 'service_resource_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    public function isInvoiced(): bool
    {
        return $this->transfer_id !== null;
    }

    /**
     * When the shop's own guarantee on the work runs out - distinct from the
     * manufacturer's warranty on the parts fitted.
     */
    public function workmanshipEndsAt(): ?\Illuminate\Support\Carbon
    {
        if ($this->delivered_at === null || $this->workmanship_warranty_days <= 0) {
            return null;
        }

        return $this->delivered_at->copy()->addDays($this->workmanship_warranty_days);
    }

    public function workmanshipStillCovered(): bool
    {
        $ends = $this->workmanshipEndsAt();

        return $ends !== null && ! $ends->isPast();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            WorkOrderStatus::Delivered->value,
            WorkOrderStatus::Cancelled->value,
        ]);
    }
}
