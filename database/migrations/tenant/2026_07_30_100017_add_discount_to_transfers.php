<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A per-order discount.
 *
 * `amount` stays what it has always been - what the customer actually owes -
 * so every existing revenue figure (reports, the balance sheet, the ledger)
 * keeps working untouched. The discount is stored ALONGSIDE it purely so the
 * invoice can show the arithmetic: the line-item subtotal is re-derivable, and
 * subtotal − discount = amount.
 *
 * Non-negative, like every other monetary column here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->decimal('discount', 10, 2)->default(0)->after('amount');
        });

        DB::connection('tenant')->statement('ALTER TABLE `transfers` ADD CONSTRAINT `transfers_discount_non_negative` CHECK (`discount` >= 0)');
    }

    public function down(): void
    {
        DB::connection('tenant')->statement('ALTER TABLE `transfers` DROP CONSTRAINT `transfers_discount_non_negative`');

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn('discount');
        });
    }
};
