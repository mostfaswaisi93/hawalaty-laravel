<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2b - the Account: a person's bank or e-wallet, each with its own
 * accounting ledger (see the ledger_entries table). Replaces the legacy
 * `banks` payment method; transfers are repointed onto accounts in Phase 2c.
 *
 * Catalog FKs are restrictOnDelete - a DB-level safety net matching the
 * service guard that forbids deleting a catalog row still referenced here.
 * `balance` is the stored running balance, kept in step with the ledger inside
 * a row-locked transaction (AccountService::postEntry); it is SIGNED (a credit
 * -heavy or overdrawn account goes negative), so no non-negative CHECK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->enum('kind', ['bank', 'wallet']);

            // Bank accounts only.
            $table->foreignUlid('bank_name_id')->nullable()->constrained('bank_names')->restrictOnDelete();
            $table->foreignUlid('account_type_id')->nullable()->constrained('account_types')->restrictOnDelete();
            $table->string('iban')->nullable();

            // Wallet accounts only.
            $table->foreignUlid('wallet_name_id')->nullable()->constrained('wallet_names')->restrictOnDelete();
            $table->foreignUlid('wallet_type_id')->nullable()->constrained('wallet_types')->restrictOnDelete();
            $table->string('wallet_phone')->nullable();

            // Shared.
            $table->foreignUlid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('holder_name');
            $table->string('phone')->nullable();
            // E.164 - the WhatsApp number order notifications are sent to (the
            // successor to the legacy banks.phone).
            $table->string('whatsapp_phone')->nullable();
            $table->decimal('balance', 15, 2)->default(0);
            $table->boolean('active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
