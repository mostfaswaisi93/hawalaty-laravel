<?php

namespace Database\Factories\Tenant;

use App\Enums\AccountKind;
use App\Models\Tenant\Account;
use App\Models\Tenant\AccountType;
use App\Models\Tenant\BankName;
use App\Models\Tenant\CashBox;
use App\Models\Tenant\Currency;
use App\Models\Tenant\WalletName;
use App\Models\Tenant\WalletType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'kind' => AccountKind::Bank,
            'bank_name_id' => BankName::factory(),
            'account_type_id' => AccountType::factory(),
            'iban' => strtoupper(fake()->bothify('PS##????########')),
            'wallet_name_id' => null,
            'wallet_type_id' => null,
            'wallet_phone' => null,
            'cash_box_id' => null,
            'currency_id' => Currency::factory(),
            'holder_name' => fake()->name(),
            'phone' => null,
            'whatsapp_phone' => null,
            'balance' => 0,
            'active' => true,
        ];
    }

    public function wallet(): static
    {
        return $this->state(fn () => [
            'kind' => AccountKind::Wallet,
            'bank_name_id' => null,
            'account_type_id' => null,
            'iban' => null,
            'wallet_name_id' => WalletName::factory(),
            'wallet_type_id' => WalletType::factory(),
            'wallet_phone' => fake()->numerify('05########'),
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn () => [
            'kind' => AccountKind::Cash,
            'bank_name_id' => null,
            'account_type_id' => null,
            'iban' => null,
            'cash_box_id' => CashBox::factory(),
        ]);
    }
}
