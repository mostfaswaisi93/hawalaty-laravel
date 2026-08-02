<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turns a catalog row from "a name and a price" into a tradeable item.
 *
 * Every field here is shared by all twelve verticals - what differs is only
 * which of them the UI bothers to show. Vertical-specific attributes (batch,
 * serial, fitment, allergens) live in their own tables so a bookshop is not
 * carrying thirty nullable pharmacy columns.
 *
 * `cost` is the reason most of this exists: without it there is no margin, no
 * cost of goods sold, and no way to value inventory under IAS 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // Services have no stock and no cost of goods; the flag keeps them out
            // of the stock ledger without needing a separate table.
            $table->enum('kind', ['goods', 'service'])->default('goods')->after('name');

            // GS1 GTIN-13/GTIN-8 for bought-in goods, or an in-store number from
            // the restricted-distribution range (prefixes 20-29) for own produce.
            $table->string('barcode', 64)->nullable()->after('kind');
            $table->string('sku', 64)->nullable()->after('barcode');

            // IFPS produce code: 4 digits conventional, 5 beginning with 9 organic.
            $table->string('plu_code', 5)->nullable()->after('sku');

            // UN/CEFACT Rec 20. Purchase unit differs when goods arrive by the box
            // and leave by the piece; the conversion is how many sales units are
            // inside one purchase unit.
            $table->string('unit_code', 8)->default('H87')->after('plu_code');
            $table->string('purchase_unit_code', 8)->nullable()->after('unit_code');
            $table->decimal('purchase_conversion', 12, 3)->nullable()->after('purchase_unit_code');

            $table->decimal('cost', 12, 3)->default(0)->after('price');

            // Per item, not per business: books are commonly zero-rated while the
            // stationery beside them is not, and a pharmacy mixes exempt dispensed
            // medicine with taxable cosmetics.
            $table->decimal('tax_rate', 5, 2)->default(0)->after('cost');
            $table->boolean('tax_included')->default(true)->after('tax_rate');

            $table->boolean('track_stock')->default(true)->after('tax_included');
            $table->decimal('reorder_point', 12, 3)->nullable()->after('track_stock');

            $table->unique('barcode');
            $table->unique('sku');
            $table->index('plu_code');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->decimal('price', 12, 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->dropUnique(['sku']);
            $table->dropIndex(['plu_code']);

            $table->dropColumn([
                'kind', 'barcode', 'sku', 'plu_code',
                'unit_code', 'purchase_unit_code', 'purchase_conversion',
                'cost', 'tax_rate', 'tax_included', 'track_stock', 'reorder_point',
            ]);

            $table->decimal('price', 10, 2)->change();
        });
    }
};
