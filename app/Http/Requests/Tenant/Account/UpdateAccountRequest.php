<?php

namespace App\Http\Requests\Tenant\Account;

use App\Enums\AccountKind;
use App\Models\Tenant\Account;
use App\Models\Tenant\BankName;
use App\Models\Tenant\CashBox;
use App\Models\Tenant\WalletName;
use App\Services\WhatsApp\PhoneNumber;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Editing an account. `kind` and `currency` are fixed at creation (the ledger
 * is denominated in that currency), so neither is here; the required fields
 * follow the account's existing kind. A changed bank/wallet must still support
 * the account's currency (withValidator).
 */
class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated in AccountController::canManage().
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
        $rules = [
            'holder_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp_phone' => ['nullable', 'string', 'max:20', 'regex:/^\+[1-9]\d{7,14}$/'],
            'active' => ['sometimes', 'boolean'],
        ];

        if ($this->account()?->kind === AccountKind::Cash) {
            $rules['cash_box_id'] = ['required', 'string', Rule::exists('tenant.cash_boxes', 'id')];
        } elseif ($this->account()?->kind === AccountKind::Bank) {
            $rules['bank_name_id'] = ['required', 'string', Rule::exists('tenant.bank_names', 'id')];
            $rules['account_type_id'] = ['required', 'string', Rule::exists('tenant.account_types', 'id')];
            $rules['iban'] = ['nullable', 'string', 'max:50'];
        } else {
            $rules['wallet_name_id'] = ['required', 'string', Rule::exists('tenant.wallet_names', 'id')];
            $rules['wallet_type_id'] = ['required', 'string', Rule::exists('tenant.wallet_types', 'id')];
            $rules['wallet_phone'] = ['required', 'string', 'max:30'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $account = $this->account();

            if ($account === null) {
                return;
            }

            $institution = match ($account->kind) {
                AccountKind::Bank => $this->input('bank_name_id') ? BankName::find($this->input('bank_name_id')) : null,
                AccountKind::Wallet => $this->input('wallet_name_id') ? WalletName::find($this->input('wallet_name_id')) : null,
                AccountKind::Cash => $this->input('cash_box_id') ? CashBox::find($this->input('cash_box_id')) : null,
            };

            if ($institution !== null && ! $institution->currencies()->whereKey($account->currency_id)->exists()) {
                $field = match ($account->kind) {
                    AccountKind::Bank => 'bank_name_id',
                    AccountKind::Wallet => 'wallet_name_id',
                    AccountKind::Cash => 'cash_box_id',
                };
                $validator->errors()->add($field, __('العملة المختارة غير مدعومة من هذا الحساب.'));
            }
        });
    }

    private function account(): ?Account
    {
        return Account::find($this->route('account'));
    }
}
