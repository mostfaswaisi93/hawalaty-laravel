<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('icon', 60)->nullable()->after('name');
        });

        Schema::table('menu_categories', function (Blueprint $table) {
            $table->string('icon', 60)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('icon');
        });

        Schema::table('menu_categories', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
