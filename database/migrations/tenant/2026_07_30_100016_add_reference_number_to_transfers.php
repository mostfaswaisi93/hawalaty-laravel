<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->string('reference_number', 100)->nullable()->after('transferred_amount');
            $table->unique('reference_number');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropUnique(['reference_number']);
            $table->dropColumn('reference_number');
        });
    }
};
