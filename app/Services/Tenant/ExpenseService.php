<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Models\Tenant\Expense;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ExpenseService
{
    /**
     * Paginated (matching History/ActivityLog) - every logged expense is a
     * new row with no ceiling, so an unbounded get() would eventually load
     * the tenant's entire expense history on one request.
     */
    public function list(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = Expense::query()->with(['account.bankName', 'account.walletName', 'account.currency'])->whereSearch('description', $search);

        return $query->latest('date')->paginate($perPage)->withQueryString();
    }

    public function create(array $data): Expense
    {
        $expense = Expense::create([
            'description' => $data['description'],
            'amount' => $data['amount'],
            'account_id' => $data['accountId'],
            'date' => $data['date'],
        ]);

        $expense->load(['account.bankName', 'account.walletName', 'account.currency']);

        AuditLogger::record(AuditAction::Created, AuditEntityType::Expense, $expense->description, AuditCategory::Expenses);

        return $expense;
    }

    public function update(Expense $expense, array $data): Expense
    {
        $update = [];

        if (array_key_exists('description', $data)) {
            $update['description'] = $data['description'];
        }
        if (array_key_exists('amount', $data)) {
            $update['amount'] = $data['amount'];
        }
        if (array_key_exists('accountId', $data)) {
            $update['account_id'] = $data['accountId'];
        }
        if (array_key_exists('date', $data)) {
            $update['date'] = $data['date'];
        }

        $expense->update($update);
        $expense->load(['account.bankName', 'account.walletName', 'account.currency']);

        AuditLogger::record(AuditAction::Updated, AuditEntityType::Expense, $expense->description, AuditCategory::Expenses);

        return $expense;
    }

    public function delete(Expense $expense): void
    {
        $label = $expense->description;
        $expense->delete();

        AuditLogger::record(AuditAction::Deleted, AuditEntityType::Expense, $label, AuditCategory::Expenses);
    }
}
