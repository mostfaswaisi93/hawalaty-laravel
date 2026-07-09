<?php

namespace App\Http\Requests\Tenant\Bank;

use App\Enums\BankType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated explicitly via $this->authorize('create', Bank::class) in
        // BankController.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(BankType::class)],
            'code' => ['required', 'string', 'max:50'],
            'active' => ['sometimes', 'boolean'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
