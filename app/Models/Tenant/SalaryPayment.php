<?php

namespace App\Models\Tenant;

use App\Enums\SalaryPeriod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A salary that was actually paid out.
 *
 * Always paired with an `expenses` row - paying a salary IS money leaving a bank
 * or wallet, so it must appear in المصروفات and the reports with no
 * special-casing. This model only carries what an expense cannot express: who
 * was paid and for which period.
 *
 * The paying account is deliberately NOT duplicated here; it is read through
 * `expense->bank`, so the two can never drift apart.
 */
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

    /**
     * withTrashed(): expenses soft-delete, and a voided expense must still show
     * which account a historical salary came out of rather than going null.
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class)->withTrashed();
    }

    /** The account the money actually left, via the expense. */
    public function bank(): ?Bank
    {
        return $this->expense?->bank;
    }
}
