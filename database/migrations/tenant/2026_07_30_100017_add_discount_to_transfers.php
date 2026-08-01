<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
