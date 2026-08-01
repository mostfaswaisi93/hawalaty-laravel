<?php

namespace App\Services\Tenant;

use App\Enums\AccountKind;
use App\Models\Tenant\Account;
use App\Models\Tenant\BankName;
use App\Models\Tenant\Currency;
use App\Models\Tenant\WalletName;
use Illuminate\Support\Facades\DB;

class LegacyBankMigrator
{
    public function migrate(): void
    {
        if (! $this->tableExists('banks')) {
            return;
        }

        $legacyBanks = DB::connection('tenant')->table('banks')->get();

        if ($legacyBanks->isEmpty()) {
            return;
        }

        DB::connection('tenant')->transaction(function () use ($legacyBanks) {
            $currency = $this->defaultCurrency();

            foreach ($legacyBanks as $bank) {
                if (Account::where('legacy_bank_id', $bank->id)->exists()) {
                    continue;
                }

                $kind = $bank->type === 'wallet' ? AccountKind::Wallet : AccountKind::Bank;
                $institution = $this->institutionFor($kind, $bank->name);
                $institution->currencies()->syncWithoutDetaching([$currency->id]);

                Account::create([
                    'kind' => $kind->value,
                    'bank_name_id' => $kind === AccountKind::Bank ? $institution->id : null,
                    'wallet_name_id' => $kind === AccountKind::Wallet ? $institution->id : null,
                    'currency_id' => $currency->id,
                    'holder_name' => $bank->name,
                    'whatsapp_phone' => $bank->phone,
                    'balance' => 0,
                    'active' => (bool) $bank->active,
                    'legacy_bank_id' => $bank->id,
                ]);
            }

            $this->backfillTransfers();
        });
    }

    private function tableExists(string $table): bool
    {
        return DB::connection('tenant')->selectOne(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
            [$table],
        ) !== null;
    }

    private function defaultCurrency(): Currency
    {
        return Currency::firstOrCreate(
            ['name' => 'شيكل'],
            ['symbol' => '₪', 'active' => true],
        );
    }

    private function institutionFor(AccountKind $kind, string $name): BankName|WalletName
    {
        $model = $kind === AccountKind::Bank ? BankName::class : WalletName::class;

        return $model::firstOrCreate(['name' => $name], ['active' => true]);
    }

    private function backfillTransfers(): void
    {
        DB::connection('tenant')->statement(
            'UPDATE `transfers` t
             INNER JOIN `accounts` a ON a.`legacy_bank_id` = t.`bank_id`
             SET t.`account_id` = a.`id`
             WHERE t.`bank_id` IS NOT NULL AND t.`account_id` IS NULL'
        );

        DB::connection('tenant')->statement(
            'UPDATE `transfers` t
             INNER JOIN `accounts` a ON a.`legacy_bank_id` = t.`change_bank_id`
             SET t.`change_account_id` = a.`id`
             WHERE t.`change_bank_id` IS NOT NULL AND t.`change_account_id` IS NULL'
        );
    }
}
