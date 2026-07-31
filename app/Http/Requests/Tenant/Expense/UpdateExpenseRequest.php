<?php

namespace App\Http\Requests\Tenant\Expense;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated explicitly via $this->authorize('update', $expense) in
        // ExpenseController.
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            // See StoreExpenseRequest for why "tenant." is required here.
            'accountId' => ['sometimes', 'required', 'string', 'exists:tenant.accounts,id'],
            'date' => ['sometimes', 'required', 'date'],
        ];
    }
}
