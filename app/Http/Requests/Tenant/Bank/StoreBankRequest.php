<?php

namespace App\Http\Requests\Tenant\Bank;

use App\Enums\BankType;
use App\Services\WhatsApp\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated explicitly via $this->authorize('create', Bank::class) in
        // BankController.
        return true;
    }

    /**
     * `phone` is the WhatsApp number new-order notifications are sent to, so it
     * is stored in E.164. Normalising here (rather than in the service) means
     * both the web form and the REST API get it, since both hand
     * $request->validated() straight to BankService.
     *
     * The merge happens ONLY when normalisation succeeded: overwriting
     * unparseable input with null would save a bank with no number at all,
     * under a green success message. Leaving it intact lets the rule below
     * reject it visibly.
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(BankType::class)],
            'code' => ['required', 'string', 'max:50', Rule::unique('tenant.banks', 'code')],
            'active' => ['sometimes', 'boolean'],
            // max:20 (not 30): E.164 is at most 16 characters including the '+'.
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+[1-9]\d{7,14}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('رقم واتساب غير صالح. اكتبه هكذا: +972595817016'),
        ];
    }
}
