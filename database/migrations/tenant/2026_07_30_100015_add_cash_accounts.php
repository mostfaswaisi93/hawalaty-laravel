<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cash becomes a third kind of account, backed by a cash box.
 *
 * Until now `cash_boxes` was a catalog nothing referenced, and every expense or
 * salary had to name a bank/wallet even when the money left the drawer. A cash
 * account closes both gaps: the drawer gets a real ledger and a real balance,
 * and "نقدي" becomes selectable wherever money moves.
 *
 * `kind` is a MySQL ENUM, so the new member has to be added to the column
 * definition or every insert is truncated (same constraint as the audit_log
 * enums).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tenant')->statement("ALTER TABLE `accounts` MODIFY `kind` ENUM('bank','wallet','cash') NOT NULL");

        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignUlid('cash_box_id')->nullable()->after('wallet_phone')->constrained('cash_boxes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['cash_box_id']);
            $table->dropColumn('cash_box_id');
        });

        DB::connection('tenant')->statement("ALTER TABLE `accounts` MODIFY `kind` ENUM('bank','wallet') NOT NULL");
    }
};
