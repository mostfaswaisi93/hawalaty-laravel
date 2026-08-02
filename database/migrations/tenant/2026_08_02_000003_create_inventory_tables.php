<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Stock as a ledger, not a counter.
 *
 * The obvious design is a `stock` column on the item that gets written on every
 * sale. It is wrong here for two concrete reasons:
 *
 *  1. This app syncs offline. Two devices that each decrement the same column
 *     from the same starting value lose one of the two decrements on push. Rows
 *     that are only ever appended cannot conflict, so the ledger survives the
 *     sync channel the counter would corrupt.
 *
 *  2. A counter cannot answer "why". Shrinkage, theft and miscounts are found by
 *     reading movement history, and an auditor asked to accept a stock valuation
 *     wants the movements behind it.
 *
 * On-hand is therefore always SUM(quantity) over the movements, signed: inflows
 * positive, outflows negative. Nothing writes a balance.
 *
 * Costing is weighted average, one of the two methods IAS 2 permits (LIFO is
 * prohibited under IFRS). It is recomputed on each receipt and frozen onto every
 * outflow, so cost of goods sold is a stored fact rather than a later guess.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('kind', 24)->default('store'); // store | warehouse | fridge | vehicle
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('name');
            $table->index(['active', 'is_default']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('tax_number', 64)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('name');
            $table->index('active');
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignUlid('location_id')->constrained('locations')->restrictOnDelete();

            $table->string('reference', 64)->nullable();
            $table->string('invoice_number', 64)->nullable();

            // draft -> received is one-way. A received purchase has already moved
            // stock, so it is reversed by a credit note, never by editing.
            $table->enum('status', ['draft', 'received', 'cancelled'])->default('draft');

            $table->date('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('other_costs', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'received_at']);
            $table->index('reference');
        });

        Schema::create('purchase_line_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignUlid('menu_item_id')->constrained('menu_items')->restrictOnDelete();

            $table->string('name');
            $table->string('unit_code', 8)->default('H87');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 12, 3);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);

            // Captured at receipt for verticals that track them; the movement rows
            // carry the same values so history stays readable on its own.
            $table->string('batch_number', 64)->nullable();
            $table->date('expires_at')->nullable();

            $table->timestamps();

            $table->index('menu_item_id');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignUlid('location_id')->constrained('locations')->restrictOnDelete();

            $table->enum('type', [
                'purchase',   // goods received
                'sale',       // sold to a customer
                'return_in',  // customer returned goods to us
                'return_out', // we returned goods to a supplier
                'adjustment', // manual correction with a reason
                'transfer',   // moved between locations (two rows, opposite signs)
                'waste',      // spoilage, breakage, expiry
                'count',      // stocktake variance
                'production', // output of a recipe
                'consumption', // components a recipe consumed
            ]);

            // Signed: positive adds, negative removes. The sign is the direction,
            // the type is the reason, and both are needed to read the ledger.
            $table->decimal('quantity', 12, 3);

            // Weighted-average cost at the moment of the movement, frozen. Cost of
            // goods sold is read from here rather than recomputed against a cost
            // that has since moved.
            $table->decimal('unit_cost', 12, 3)->default(0);

            $table->string('batch_number', 64)->nullable();
            $table->date('expires_at')->nullable();
            $table->string('serial_number', 64)->nullable();

            // Points at whatever caused this: a transfer, a purchase, a count.
            $table->string('reference_type', 32)->nullable();
            $table->ulid('reference_id')->nullable();

            $table->string('reason')->nullable();
            $table->foreignUlid('team_member_id')->nullable()->constrained('team_members')->nullOnDelete();

            $table->timestamps();

            // On-hand is a SUM over (item, location); this is the index that makes
            // that affordable on every catalog screen.
            $table->index(['menu_item_id', 'location_id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['type', 'created_at']);
            $table->index(['menu_item_id', 'expires_at']);
            $table->index('serial_number');
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('location_id')->constrained('locations')->restrictOnDelete();
            $table->string('reference', 64)->nullable();
            $table->enum('status', ['open', 'posted', 'cancelled'])->default('open');
            $table->timestamp('posted_at')->nullable();
            $table->foreignUlid('team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'posted_at']);
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignUlid('menu_item_id')->constrained('menu_items')->cascadeOnDelete();

            // What the ledger said when the sheet was opened, versus what was
            // actually on the shelf. Storing the expected value makes the variance
            // reproducible after later movements have shifted the balance.
            $table->decimal('expected_quantity', 12, 3)->default(0);
            $table->decimal('counted_quantity', 12, 3)->nullable();
            $table->decimal('unit_cost', 12, 3)->default(0);

            // Empty rather than null: MySQL treats NULLs as distinct in a unique
            // index, so a nullable column here would let the same item be counted
            // twice on one sheet - exactly what the constraint exists to stop.
            $table->string('batch_number', 64)->default('');
            $table->timestamps();

            $table->unique(['stock_count_id', 'menu_item_id', 'batch_number'], 'stock_count_lines_unique_item');
        });

        // Every movement needs somewhere to be. A single-shop tenant never opens
        // the locations screen, so it has to already have one.
        DB::connection('tenant')->table('locations')->insert([
            'id' => (string) Str::ulid(),
            'name' => __('المحل'),
            'kind' => 'store',
            'is_default' => true,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('purchase_line_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('locations');
    }
};
