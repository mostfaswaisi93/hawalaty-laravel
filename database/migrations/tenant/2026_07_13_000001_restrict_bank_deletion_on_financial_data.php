<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hardens the banks.id foreign keys so a bank with financial history can
 * never be hard-deleted at the database level: expenses.bank_id previously
 * cascade-deleted (silently destroying expense records), and
 * transfers.bank_id / change_bank_id previously nulled out (silently
 * erasing which bank a historical transfer used). Both are replaced with
 * restrictOnDelete() - the application layer (BankService::delete) checks
 * for related records first and returns a friendly validation error; this
 * constraint is the last-resort safety net if that check is ever bypassed.
 * Banks should be deactivated (the existing `active` toggle), not deleted,
 * once they have any history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->foreign('bank_id')->references('id')->on('banks')->restrictOnDelete();
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->foreign('bank_id')->references('id')->on('banks')->restrictOnDelete();

            $table->dropForeign(['change_bank_id']);
            $table->foreign('change_bank_id')->references('id')->on('banks')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->foreign('bank_id')->references('id')->on('banks')->cascadeOnDelete();
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->foreign('bank_id')->references('id')->on('banks')->nullOnDelete();

            $table->dropForeign(['change_bank_id']);
            $table->foreign('change_bank_id')->references('id')->on('banks')->nullOnDelete();
        });
    }
};
