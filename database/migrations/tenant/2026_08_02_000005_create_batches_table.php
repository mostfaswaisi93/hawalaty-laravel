<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot tracking.
 *
 * The movements already carry a denormalised batch number and expiry so history
 * reads on its own, but a lot needs to exist as a row for two things the ledger
 * cannot express:
 *
 *  - A lot that has sold out still has to be findable. A recall arrives after
 *    the stock has gone, and "which lot, expiring when, from which supplier"
 *    must survive the balance reaching zero.
 *
 *  - Expiry belongs to the lot, not to each movement. Correcting a mistyped
 *    expiry date has to fix one row, not rewrite an append-only ledger.
 *
 * Balances stay derived: a lot's quantity on hand is SUM(quantity) over the
 * movements pointing at it, exactly as with everything else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('menu_item_id')->constrained('menu_items')->cascadeOnDelete();

            $table->string('batch_number', 64);

            // Nullable: a lot number without an expiry is legitimate. Auto parts
            // and tobacco stamps are tracked by lot and never expire.
            $table->date('expires_at')->nullable();

            $table->foreignUlid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // One lot number per item. The same number from two suppliers is the
            // same lot as far as the shelf is concerned.
            $table->unique(['menu_item_id', 'batch_number']);

            // FEFO orders by this on every issue, and the expiry report scans it.
            $table->index('expires_at');
            $table->index(['menu_item_id', 'expires_at']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignUlid('batch_id')->nullable()->after('location_id')
                ->constrained('batches')->nullOnDelete();

            // Per-lot balance is a grouped sum over this.
            $table->index(['batch_id', 'menu_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['batch_id', 'menu_item_id']);
            $table->dropConstrainedForeignId('batch_id');
        });

        Schema::dropIfExists('batches');
    }
};
