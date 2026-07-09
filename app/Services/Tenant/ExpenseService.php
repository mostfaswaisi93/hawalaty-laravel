<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Models\Tenant\Expense;
use Illuminate\Database\Eloquent\Collection;

class ExpenseService
{
    public function list(?string $search = null): Collection
    {
        $query = Expense::query()->with('bank');

        if ($search) {
            $query->where('description', 'like', "%{$search}%");
        }

        return $query->latest('date')->get();
    }

    public function create(array $data): Expense
    {
        $expense = Expense::create([
            'description' => $data['description'],
            'amount' => $data['amount'],
            'bank_id' => $data['bankId'],
            'date' => $data['date'],
        ]);

        $expense->load('bank');

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
        if (array_key_exists('bankId', $data)) {
            $update['bank_id'] = $data['bankId'];
        }
        if (array_key_exists('date', $data)) {
            $update['date'] = $data['date'];
        }

        $expense->update($update);
        $expense->load('bank');

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
