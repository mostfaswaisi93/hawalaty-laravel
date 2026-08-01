<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An icon per catalogue entry and per category.
 *
 * Nullable: an entry without one falls back to its category's icon, then to a
 * neutral default.
 *
 * Stores a Material Symbols LIGATURE NAME (e.g. "nutrition"), not a file - the
 * font is already loaded, so it costs no request and inherits theme colour. The
 * rendering side WHITELISTS what it draws, so a hand-edited value cannot inject
 * markup.
 */
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
