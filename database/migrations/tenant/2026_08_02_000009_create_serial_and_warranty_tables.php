<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Serial numbers and warranty.
 *
 * A lot tracks a group; a serial tracks one physical unit. The difference
 * matters the moment a customer comes back holding a phone: the question is not
 * "did we sell this model" but "did we sell this handset, when, and is it still
 * under warranty". Only an identity per unit answers that.
 *
 * The quantity ledger stays the truth for how many. Serials are the identity
 * layer above it, and the two are kept in step by writing one movement per
 * serial - so counting in-stock serials and summing the ledger give the same
 * number, and any drift is visible rather than assumed away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // Distinct from track_stock: a serialised item is stocked AND
            // identified. Turning this on makes serials mandatory at both ends.
            $table->boolean('track_serials')->default(false)->after('track_stock');

            // The default term a sale of this item carries. Zero means none.
            $table->unsignedSmallInteger('warranty_months')->default(0)->after('track_serials');
        });

        Schema::create('serial_numbers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('menu_item_id')->constrained('menu_items')->cascadeOnDelete();

            // IMEI, chassis number, device serial. Unique per item rather than
            // globally: two different products may legitimately reuse a number
            // from two different manufacturers.
            $table->string('serial', 64);

            // Pharmacy carries both - GS1 DataMatrix holds lot and serial on the
            // same pack - so a serial may belong to a lot.
            $table->foreignUlid('batch_id')->nullable()->constrained('batches')->nullOnDelete();

            $table->enum('status', ['in_stock', 'sold', 'returned', 'scrapped'])->default('in_stock');
            $table->foreignUlid('location_id')->nullable()->constrained('locations')->nullOnDelete();

            $table->decimal('unit_cost', 12, 3)->default(0);
            $table->timestamp('received_at')->nullable();
            $table->foreignUlid('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();

            $table->timestamp('sold_at')->nullable();
            $table->foreignUlid('transfer_id')->nullable()->constrained('transfers')->nullOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // Copied from the item at the moment of sale, then frozen: changing
            // the catalogue's warranty next year must not shorten or extend what
            // a customer was already promised.
            $table->unsignedSmallInteger('warranty_months')->default(0);
            $table->date('warranty_starts_at')->nullable();
            $table->date('warranty_ends_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['menu_item_id', 'serial']);
            $table->index(['status', 'menu_item_id']);
            $table->index('serial');
            $table->index('warranty_ends_at');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignUlid('serial_id')->nullable()->after('batch_id')
                ->constrained('serial_numbers')->nullOnDelete();

            $table->index('serial_id');
        });

        Schema::table('purchase_line_items', function (Blueprint $table) {
            // Captured while the order is still a draft, turned into rows when
            // it is received. Held as JSON because until then they are just a
            // list someone typed off a delivery note, not yet stock.
            $table->json('serials')->nullable()->after('expires_at');
        });

        Schema::create('warranty_claims', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Nullable because not everything under warranty is serialised - a
            // part fitted last month may only be identified by its receipt.
            $table->foreignUlid('serial_number_id')->nullable()->constrained('serial_numbers')->nullOnDelete();
            $table->foreignUlid('menu_item_id')->constrained('menu_items')->restrictOnDelete();
            $table->foreignUlid('transfer_id')->nullable()->constrained('transfers')->nullOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->string('reference', 64)->nullable();
            $table->string('customer_name');
            $table->string('customer_phone', 50)->nullable();

            $table->text('issue');
            $table->enum('status', ['open', 'in_repair', 'resolved', 'rejected', 'replaced'])->default('open');

            // Whether the shop is carrying the cost, which is the commercial
            // question behind every claim.
            $table->boolean('under_warranty')->default(true);
            $table->decimal('repair_cost', 12, 2)->default(0);
            $table->decimal('charged_amount', 12, 2)->default(0);

            $table->text('resolution')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUlid('team_member_id')->nullable()->constrained('team_members')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'reported_at']);
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_claims');

        Schema::table('purchase_line_items', function (Blueprint $table) {
            $table->dropColumn('serials');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['serial_id']);
            $table->dropConstrainedForeignId('serial_id');
        });

        Schema::dropIfExists('serial_numbers');

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['track_serials', 'warranty_months']);
        });
    }
};
