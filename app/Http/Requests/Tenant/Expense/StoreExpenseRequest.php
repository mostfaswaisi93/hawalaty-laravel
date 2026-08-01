<?php

namespace App\Http\Requests\Tenant\Expense;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],

            'accountId' => ['required', 'string', 'exists:tenant.accounts,id'],
            'date' => ['required', 'date'],
        ];
    }
}
