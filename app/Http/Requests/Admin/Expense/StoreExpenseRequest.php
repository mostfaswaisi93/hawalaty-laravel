<?php

namespace App\Http\Requests\Admin\Expense;

use Illuminate\Foundation\Http\FormRequest;

/**
 * For the platform's OWN operating expenses (App\Models\Expense) — distinct
 * from App\Http\Requests\Tenant\Expense\StoreExpenseRequest, which validates
 * a tenant's own expenses on the dynamic `tenant` connection.
 */
class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'boxId' => ['required', 'string', 'exists:boxes,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
        ];
    }
}
