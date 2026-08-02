<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a job: a part off the shelf or an hour of labour.
 *
 * Both point at a catalogue item, which is the whole trick - it means the
 * finished job can be billed through the ordinary till rather than needing an
 * invoicing path of its own.
 */
#[Fillable(['work_order_id', 'menu_item_id', 'name', 'quantity', 'unit_price', 'line_total', 'position'])]
class WorkOrderLine extends Model
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

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function isLabour(): bool
    {
        return $this->item?->isService() ?? false;
    }
}
