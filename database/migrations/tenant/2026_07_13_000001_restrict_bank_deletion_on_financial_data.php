<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * restrictOnDelete() on the banks.id foreign keys, so a bank with financial
 * history can never be hard-deleted at the database level.
 *
 * BankService::delete checks first and returns a friendly error; this is the
 * last-resort net if that check is bypassed. Banks with history should be
 * DEACTIVATED via the `active` toggle, not deleted.
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
