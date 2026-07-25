<?php

namespace App\Http\Requests\Tenant\Expense;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated explicitly via $this->authorize('create', Expense::class)
        // in ExpenseController.
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            // "tenant." prefix is required - a bare exists:banks,id
            // resolves against the app's DEFAULT connection (platform),
            // not the dynamic `tenant` connection ResolveTenantConnection
            // configured for this request. Verified via a real 500 error
            // during this task's curl testing before this fix.
            'bankId' => ['required', 'string', 'exists:tenant.banks,id'],
            'date' => ['required', 'date'],
        ];
    }
}
