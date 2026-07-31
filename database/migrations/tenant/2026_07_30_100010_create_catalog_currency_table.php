<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2b - which currencies each bank / wallet / cash box supports.
 *
 * A polymorphic pivot shared by all three currency-bearing catalogs (BankName,
 * WalletName, CashBox), so one table + one morphToMany covers them uniformly.
 * An account opened at a bank/wallet may only pick a currency that bank/wallet
 * supports (enforced in StoreAccountRequest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_currency', function (Blueprint $table) {
            $table->ulid('catalog_id');
            $table->string('catalog_type');
            $table->foreignUlid('currency_id')->constrained('currencies')->cascadeOnDelete();

            $table->index(['catalog_type', 'catalog_id']);
            $table->unique(['catalog_type', 'catalog_id', 'currency_id'], 'catalog_currency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_currency');
    }
};
