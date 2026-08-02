<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Size x colour matrices.
 *
 * A shirt in five sizes and four colours is twenty stock-keeping units, each
 * with its own shelf count and its own barcode - GS1 requires a distinct GTIN
 * per variant, not one for the style.
 *
 * The variant is therefore a menu_item in its own right, hanging off a parent
 * that is never sold. The alternative - a separate variants table - would mean
 * the stock ledger, purchase lines, sale lines and batches all had to point at
 * either an item or a variant, and every one of those joins would grow a
 * polymorphic branch. Reusing the item row means the entire module built so far
 * works on variants for free.
 *
 * Axes reuse the constant lists the vertical already seeds ("المقاسات",
 * "الألوان"), so the values are a managed set rather than free text - "أحمر"
 * and "احمر" as two colours would break the matrix quietly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // Set on a variant, null on everything else. A parent is identified
            // by having children, so there is no flag to fall out of step.
            $table->foreignUlid('parent_item_id')->nullable()->after('menu_category_id')
                ->constrained('menu_items')->cascadeOnDelete();

            $table->index('parent_item_id');
        });

        // Which axes a style varies on, and in what order the matrix reads.
        Schema::create('menu_item_variant_axes', function (Blueprint $table) {
            $table->foreignUlid('parent_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignUlid('constant_list_id')->constrained('constant_lists')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);

            $table->primary(['parent_item_id', 'constant_list_id'], 'menu_item_variant_axes_primary');
        });

        // Where one variant sits on each axis.
        Schema::create('menu_item_variant_values', function (Blueprint $table) {
            $table->foreignUlid('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignUlid('constant_list_id')->constrained('constant_lists')->cascadeOnDelete();

            // Restricted: deleting a size that variants are built on would leave
            // rows that can no longer say what they are.
            $table->foreignUlid('constant_list_item_id')->constrained('constant_list_items')->restrictOnDelete();

            $table->primary(['menu_item_id', 'constant_list_id'], 'menu_item_variant_values_primary');
            $table->index('constant_list_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_variant_values');
        Schema::dropIfExists('menu_item_variant_axes');

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_item_id');
        });
    }
};
