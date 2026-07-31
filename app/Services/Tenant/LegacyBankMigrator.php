<?php

namespace App\Services\Tenant;

use App\Enums\AccountKind;
use App\Models\Tenant\Account;
use App\Models\Tenant\BankName;
use App\Models\Tenant\Currency;
use App\Models\Tenant\WalletName;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2c - moves every legacy `banks` row into the new accounts model and
 * repoints existing orders onto the new accounts.
 *
 * Extracted from the migration so it can be unit-tested against seeded legacy
 * data (the migration itself runs once, on an empty fresh database).
 *
 * Decisions (confirmed with the product owner):
 *  - migrated accounts are denominated in a default شيكل ₪ currency (the app
 *    was single-currency), which is also marked supported on each migrated
 *    bank/wallet so accounts opened there afterwards can use it;
 *  - the legacy bank's WhatsApp number (banks.phone) becomes the account's
 *    whatsapp_phone, so order notifications keep reaching the same person;
 *  - the old transfers.bank_id / change_bank_id are LEFT in place beside the new
 *    account_id / change_account_id, so historical receipts are untouched.
 *
 * Idempotent: a legacy bank already migrated (matched by accounts.legacy_bank_id)
 * is skipped, and the backfill only fills nulls.
 */
class LegacyBankMigrator
{
    public function migrate(): void
    {
        // Nothing to do once the legacy table is gone, or before it exists.
        //
        // A plain information_schema SELECT, NOT Schema::hasTable(): the schema
        // builder's machinery can force an implicit COMMIT of the surrounding
        // transaction on MySQL, which would break the test harness's per-test
        // rollback (and defeat this method's own transaction).
        if (! $this->tableExists('banks')) {
            return;
        }

        // Read the legacy banks BEFORE opening the transaction or creating any
        // default data: with no banks there is nothing to migrate, and creating
        // the default currency anyway would leak a committed row into an
        // otherwise-empty database (e.g. a fresh migrate on a brand-new tenant).
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
        // A literal, not __(): a currency name is stored data, not UI copy.
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

    /**
     * Point each order at the account its legacy bank became. Multi-table
     * UPDATE joined on the provenance column; only unfilled links are touched.
     */
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
