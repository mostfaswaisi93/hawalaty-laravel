<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An icon per catalogue entry and per category.
 *
 * With the catalogue no longer assumed to be food, a single generic glyph for
 * everything stops carrying meaning: a fruit shop wants an apple beside apples
 * and a fuel station wants a pump beside diesel. Nullable, so anything without
 * one keeps falling back to the category's icon and then to a neutral default.
 *
 * Stores a Material Symbols LIGATURE NAME (e.g. "nutrition"), not a file - the
 * icon font is already loaded on every page, so this costs no extra request and
 * inherits the theme's colour automatically. The rendering side whitelists what
 * it will draw, so a hand-edited value cannot inject markup.
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
