<?php

namespace App\Services\Tenant;

use App\Models\Tenant\AccountType;
use App\Models\Tenant\BankName;
use App\Models\Tenant\CashBox;
use App\Models\Tenant\Currency;
use App\Models\Tenant\WalletName;
use App\Models\Tenant\WalletType;
use Illuminate\Database\Eloquent\Model;

class CatalogRegistry
{
    public function all(): array
    {
        return [
            'currencies' => [
                'model' => Currency::class,
                'singular' => __('عملة'),
                'plural' => __('العملات'),
                'icon' => 'payments',
                'symbol' => true,
                'currencies' => false,
                'fk' => 'currency_id',
                'placeholder' => __('مثال: شيكل'),
                'hint' => __('العملات التي تُمسك بها أرصدة الحسابات، ورمز كل عملة.'),
            ],
            'banks' => [
                'model' => BankName::class,
                'singular' => __('بنك'),
                'plural' => __('البنوك'),
                'icon' => 'account_balance',
                'symbol' => false,
                'currencies' => true,
                'fk' => 'bank_name_id',
                'placeholder' => __('مثال: بنك فلسطين'),
                'hint' => __('أسماء البنوك التي تُفتح فيها الحسابات، والعملات التي يدعمها كل بنك.'),
            ],
            'wallets' => [
                'model' => WalletName::class,
                'singular' => __('محفظة'),
                'plural' => __('المحافظ'),
                'icon' => 'account_balance_wallet',
                'symbol' => false,
                'currencies' => true,
                'fk' => 'wallet_name_id',
                'placeholder' => __('مثال: جوّال باي'),
                'hint' => __('أسماء المحافظ الإلكترونية التي تُفتح فيها الحسابات، والعملات التي تدعمها كل محفظة.'),
            ],
            'account-types' => [
                'model' => AccountType::class,
                'singular' => __('نوع حساب'),
                'plural' => __('أنواع الحسابات'),
                'icon' => 'category',
                'symbol' => false,
                'currencies' => false,
                'fk' => 'account_type_id',
                'placeholder' => __('مثال: توفير'),
                'hint' => __('أنواع الحسابات البنكية (توفير، جاري، شمول مالي...).'),
            ],
            'wallet-types' => [
                'model' => WalletType::class,
                'singular' => __('نوع محفظة'),
                'plural' => __('أنواع المحافظ'),
                'icon' => 'sell',
                'symbol' => false,
                'currencies' => false,
                'fk' => 'wallet_type_id',
                'placeholder' => __('مثال: تجارية'),
                'hint' => __('أنواع المحافظ (تجارية، شخصية...).'),
            ],
            'cash-boxes' => [
                'model' => CashBox::class,
                'singular' => __('صندوق نقدي'),
                'plural' => __('الصناديق النقدية'),
                'icon' => 'savings',
                'symbol' => false,
                'currencies' => true,
                'fk' => null,
                'placeholder' => __('مثال: صندوق المحل'),
                'hint' => __('صناديق النقد التي يُحفظ فيها الكاش، والعملات التي يحملها كل صندوق.'),
            ],
        ];
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function get(string $key): array
    {
        abort_unless($this->has($key), 404);

        return $this->all()[$key];
    }

    public function modelFor(string $key): string
    {
        return $this->get($key)['model'];
    }
}
