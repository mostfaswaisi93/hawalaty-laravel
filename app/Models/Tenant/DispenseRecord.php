<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One dispense, written once.
 *
 * The controlled-substance register. Nothing in the application updates or
 * deletes a row here: a correction is a new record, because this is the document
 * an inspector reads and a register that can be rewritten is not a register.
 */
#[Fillable([
    'menu_item_id', 'transfer_id', 'batch_id', 'customer_id',
    'patient_name', 'patient_phone', 'patient_id_number',
    'prescriber_name', 'prescriber_licence', 'prescription_number', 'prescription_date',
    'quantity', 'control_schedule', 'was_prescription', 'notes', 'team_member_id', 'dispensed_at',
])]
class DispenseRecord extends Model
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
            'was_prescription' => 'boolean',
            'prescription_date' => 'date',
            'dispensed_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    public function isControlled(): bool
    {
        return filled($this->control_schedule);
    }

    public function scopeControlled(Builder $query): Builder
    {
        return $query->whereNotNull('control_schedule');
    }
}
