<?php

use App\Services\Tenant\LegacyBankMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2e - retires the legacy `banks` table for good.
 *
 * Phase 2c moved the DATA onto accounts and repointed orders, but left `banks`
 * standing because expenses still required one. This finishes the job:
 * expenses move onto accounts, the orders' legacy columns go, and the table is
 * dropped. After this the accounting model is the only one there is.
 *
 * The migrator runs FIRST, and is idempotent: it catches any bank created after
 * Phase 2c ran (the /banks screen still existed until now) and re-backfills
 * `transfers.account_id`, so no expense or order is left pointing at nothing.
 *
 * NOT reversible - down() cannot invent the retired table's rows back. The data
 * lives on in `accounts`, each carrying its origin in `legacy_bank_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('banks')) {
            return;
        }

        // Sweep up any late-created bank, and (re)fill transfers.account_id.
        app(LegacyBankMigrator::class)->migrate();

        // ── expenses: bank_id -> account_id ──
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignUlid('account_id')->nullable()->after('bank_id')->constrained('accounts')->restrictOnDelete();
        });

        DB::connection('tenant')->statement(
            'UPDATE `expenses` e
             INNER JOIN `accounts` a ON a.`legacy_bank_id` = e.`bank_id`
             SET e.`account_id` = a.`id`
             WHERE e.`account_id` IS NULL'
        );

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->dropColumn('bank_id');
        });

        // Every expense named a bank (the old column was NOT NULL) and every
        // bank became an account, so the backfill is total - restore the same
        // guarantee on the new column.
        DB::connection('tenant')->statement('ALTER TABLE `expenses` MODIFY `account_id` CHAR(26) NOT NULL');

        // ── transfers: drop the legacy links, account_id already carries them ──
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->dropColumn('bank_id');
            $table->dropForeign(['change_bank_id']);
            $table->dropColumn('change_bank_id');
        });

        Schema::drop('banks');
    }

    public function down(): void
    {
        // One-way: the legacy rows cannot be reconstructed. See the class docblock.
    }
};
