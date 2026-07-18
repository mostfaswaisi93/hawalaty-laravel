<?php

namespace App\Http\Requests\Tenant\Bank;

use App\Enums\BankType;
use App\Services\WhatsApp\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated explicitly via $this->authorize('update', $bank) in
        // BankController.
        return true;
    }

    /**
     * See StoreBankRequest::prepareForValidation() — same rules. The has()
     * guard preserves partial-update semantics: an API caller who omits `phone`
     * must not have it touched.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('phone')) {
            return;
        }

        $normalised = PhoneNumber::toE164($this->input('phone'));

        if ($normalised !== null) {
            $this->merge(['phone' => $normalised]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', new Enum(BankType::class)],
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('tenant.banks', 'code')->ignore($this->route('bank'))],
            'active' => ['sometimes', 'boolean'],
            // `sometimes` added for consistency with every other field here.
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\+[1-9]\d{7,14}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('رقم واتساب غير صالح. اكتبه هكذا: +972595817016'),
        ];
    }
}
