<?php

namespace App\Http\Requests\Tenant\Bank;

use App\Enums\BankType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated explicitly via $this->authorize('update', $bank) in
        // BankController.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', new Enum(BankType::class)],
            'code' => ['sometimes', 'required', 'string', 'max:50'],
            'active' => ['sometimes', 'boolean'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
