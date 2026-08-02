<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allergen declarations, per item.
 *
 * Declared against ingredients rather than dishes wherever possible: flour is
 * gluten once, and every dish that reaches flour through its recipe inherits it.
 * Declaring on the dish instead means the day someone changes the recipe, the
 * label silently becomes a lie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_allergens', function (Blueprint $table) {
            $table->foreignUlid('menu_item_id')->constrained('menu_items')->cascadeOnDelete();

            // The fourteen of EU 1169/2011 Annex II, held as a string rather than
            // an enum column so adding one is a code change, not a migration on
            // every tenant database.
            $table->string('allergen', 32);

            $table->enum('presence', ['contains', 'may_contain'])->default('contains');

            $table->primary(['menu_item_id', 'allergen']);
        });

        Schema::table('menu_items', function (Blueprint $table) {
            /*
             * When someone last checked - and the reason this column exists.
             *
             * An item with no allergen rows is ambiguous: it could mean "we
             * looked, there are none" or "nobody has ever looked". Those are
             * opposite answers to an inspector, and only the second is a
             * problem. The timestamp separates them.
             */
            $table->timestamp('allergens_reviewed_at')->nullable()->after('reorder_point');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('allergens_reviewed_at');
        });

        Schema::dropIfExists('menu_item_allergens');
    }
};
