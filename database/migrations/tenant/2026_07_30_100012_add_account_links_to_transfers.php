<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2c (schema half) - the columns the legacy-bank migration fills.
 *
 * `transfers.account_id` / `change_account_id` are the new Account links; the
 * old `bank_id` / `change_bank_id` stay untouched beside them so historical
 * receipts keep resolving their bank name. `accounts.legacy_bank_id` records
 * which legacy bank an account was migrated from (provenance + idempotency +
 * the join used to backfill transfers). Data is moved in the next migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->foreignUlid('account_id')->nullable()->after('bank_id')->constrained('accounts')->nullOnDelete();
            $table->foreignUlid('change_account_id')->nullable()->after('change_bank_id')->constrained('accounts')->nullOnDelete();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->ulid('legacy_bank_id')->nullable()->after('id');
            $table->index('legacy_bank_id');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
            $table->dropForeign(['change_account_id']);
            $table->dropColumn('change_account_id');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex(['legacy_bank_id']);
            $table->dropColumn('legacy_bank_id');
        });
    }
};
