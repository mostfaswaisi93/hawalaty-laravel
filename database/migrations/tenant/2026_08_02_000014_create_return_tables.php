<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Returns.
 *
 * The one module every vertical needs, and the one whose rules differ most
 * between them - a shirt goes back on the rail, a sandwich goes in the bin.
 *
 * The accounting shape is fixed regardless: a sale is never edited or deleted.
 * A return is its own document, pointing at the original, and the ledger ends up
 * holding both. Reversing by deletion would make the day's takings depend on
 * when you looked at them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Nullable: a customer without a receipt is still a customer, and
            // refusing to record the return does not make it not happen.
            $table->foreignUlid('transfer_id')->nullable()->constrained('transfers')->nullOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->string('reference', 64)->nullable();
            $table->string('customer_name');
            $table->string('customer_phone', 50)->nullable();

            $table->enum('status', ['draft', 'completed', 'rejected'])->default('draft');
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();

            $table->enum('refund_method', ['cash', 'bank', 'store_credit'])->default('cash');
            $table->foreignUlid('account_id')->nullable()->constrained('accounts')->nullOnDelete();

            $table->decimal('subtotal', 14, 2)->default(0);

            // Charged on some trades to cover the cost of putting a returned
            // item back into sellable condition.
            $table->decimal('restocking_fee', 14, 2)->default(0);
            $table->decimal('refund_total', 14, 2)->default(0);

            $table->timestamp('returned_at')->nullable();
            $table->foreignUlid('team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'returned_at']);
            $table->index('transfer_id');
            $table->index('reference');
        });

        Schema::create('sale_return_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();

            // Links back to the exact line being reversed where there is one, so
            // a partial return cannot exceed what was sold.
            $table->foreignUlid('transfer_line_item_id')->nullable()->constrained('transfer_line_items')->nullOnDelete();
            $table->foreignUlid('menu_item_id')->constrained('menu_items')->restrictOnDelete();

            $table->string('name');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 3)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);

            // Whether this piece can be sold again. Forced to `damaged` in the
            // trades where nothing that left may come back.
            $table->enum('condition', ['resalable', 'damaged'])->default('resalable');

            $table->foreignUlid('serial_id')->nullable()->constrained('serial_numbers')->nullOnDelete();
            $table->foreignUlid('batch_id')->nullable()->constrained('batches')->nullOnDelete();

            $table->timestamps();

            $table->index('sale_return_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            // Credit issued instead of cash, spendable on a later visit. Held on
            // the customer rather than as a voucher because a shop that loses
            // the slip still owes the money.
            $table->decimal('store_credit', 14, 2)->default(0)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('store_credit');
        });

        Schema::dropIfExists('sale_return_lines');
        Schema::dropIfExists('sale_returns');
    }
};
