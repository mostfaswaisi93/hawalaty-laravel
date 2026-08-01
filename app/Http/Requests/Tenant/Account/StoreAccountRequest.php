<?php

namespace App\Http\Requests\Tenant\Account;

use App\Enums\AccountKind;
use App\Models\Tenant\BankName;
use App\Models\Tenant\CashBox;
use App\Models\Tenant\WalletName;
use App\Services\WhatsApp\PhoneNumber;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;

    }

    protected function prepareForValidation(): void
    {
        if ($this->has('whatsapp_phone')) {
            $normalised = PhoneNumber::toE164($this->input('whatsapp_phone'));

            if ($normalised !== null) {
                $this->merge(['whatsapp_phone' => $normalised]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', new Enum(AccountKind::class)],
            'holder_name' => ['required', 'string', 'max:255'],
            'currency_id' => ['required', 'string', Rule::exists('tenant.currencies', 'id')],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp_phone' => ['nullable', 'string', 'max:20', 'regex:/^\+[1-9]\d{7,14}$/'],
            'opening_balance' => ['nullable', 'numeric', 'between:-9999999999999,9999999999999'],

            'bank_name_id' => ['nullable', 'required_if:kind,bank', 'string', Rule::exists('tenant.bank_names', 'id')],
            'account_type_id' => ['nullable', 'required_if:kind,bank', 'string', Rule::exists('tenant.account_types', 'id')],
            'iban' => ['nullable', 'string', 'max:50'],

            'wallet_name_id' => ['nullable', 'required_if:kind,wallet', 'string', Rule::exists('tenant.wallet_names', 'id')],
            'wallet_type_id' => ['nullable', 'required_if:kind,wallet', 'string', Rule::exists('tenant.wallet_types', 'id')],
            'wallet_phone' => ['nullable', 'required_if:kind,wallet', 'string', 'max:30'],

            'cash_box_id' => ['nullable', 'required_if:kind,cash', 'string', Rule::exists('tenant.cash_boxes', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $kind = $this->input('kind');
            $currencyId = $this->input('currency_id');

            if (! $currencyId) {
                return;
            }

            $institution = match ($kind) {
                AccountKind::Bank->value => $this->input('bank_name_id') ? BankName::find($this->input('bank_name_id')) : null,
                AccountKind::Wallet->value => $this->input('wallet_name_id') ? WalletName::find($this->input('wallet_name_id')) : null,
                AccountKind::Cash->value => $this->input('cash_box_id') ? CashBox::find($this->input('cash_box_id')) : null,
                default => null,
            };

            if ($institution === null) {
                return;

            }

            if (! $institution->currencies()->whereKey($currencyId)->exists()) {
                $validator->errors()->add('currency_id', __('العملة المختارة غير مدعومة من هذا الحساب.'));
            }
        });
    }
}
