<?php

namespace App\Models\Tenant;

use App\Enums\SalaryPeriod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['team_member_id', 'expense_id', 'amount', 'period', 'paid_for', 'notes'])]
class SalaryPayment extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'period' => SalaryPeriod::class,
            'paid_for' => 'date',
        ];
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class)->withTrashed();
    }

    public function account(): ?Account
    {
        return $this->expense?->account;
    }
}
