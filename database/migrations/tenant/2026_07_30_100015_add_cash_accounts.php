<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
