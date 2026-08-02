<?php

namespace App\Models\Tenant;

use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'menu_item_id', 'service_resource_id', 'customer_id',
    'customer_name', 'customer_phone', 'starts_at', 'ends_at',
    'status', 'deposit', 'notes', 'team_member_id',
])]
class Appointment extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'deposit' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(ServiceResource::class, 'service_resource_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    public function minutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }

    public function timeLabel(): string
    {
        return $this->starts_at->format('H:i').' - '.$this->ends_at->format('H:i');
    }

    /**
     * Bookings that still occupy their slot on a given day.
     */
    public function scopeHolding(Builder $query): Builder
    {
        return $query->whereIn('status', [
            AppointmentStatus::Booked->value,
            AppointmentStatus::Confirmed->value,
            AppointmentStatus::InProgress->value,
        ]);
    }

    public function scopeOnDay(Builder $query, string $date): Builder
    {
        return $query->whereDate('starts_at', $date);
    }
}
