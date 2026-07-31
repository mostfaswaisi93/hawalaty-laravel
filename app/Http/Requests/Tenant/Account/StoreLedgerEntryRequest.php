<?php

namespace App\Http\Requests\Tenant\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A manual ledger movement: a direction (مدين / دائن) and a positive amount,
 * with an optional note. The service turns this into a debit-or-credit entry
 * and moves the running balance.
 */
class StoreLedgerEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated in AccountController::canManage().
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['debit', 'credit'])],
            // decimal(10,2) column - cap at its max and require a positive move.
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
