<?php

namespace App\Http\Requests\Tenant\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLedgerEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;

    }

    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['debit', 'credit'])],

            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
