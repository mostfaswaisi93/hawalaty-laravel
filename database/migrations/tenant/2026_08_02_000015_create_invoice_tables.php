<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Electronic invoices.
 *
 * Built to EN 16931 - the European semantic standard that every e-invoicing
 * regime in this region derives from: ZATCA in Saudi Arabia, ETA in Egypt,
 * JoFotara in Jordan, and anything PEPPOL-connected. The syntax is UBL 2.1.
 *
 * What this module does: holds the fields the standard requires, numbers
 * invoices without gaps, renders valid UBL, and chains each document to the one
 * before it so a retroactive edit cannot hide.
 *
 * What it deliberately does NOT do: sign with a tax-authority certificate, or
 * submit anything for clearance. Both are per-jurisdiction, need credentials
 * this application has no business holding, and claiming them without doing
 * them would be worse than not offering them. The document produced here is the
 * input to that step, not a substitute for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            /*
             * UNCL 5305 tax category. A zero rate and an exemption both charge
             * nothing and are not the same thing - books are zero-rated, a
             * dispensed medicine is often exempt - and the invoice has to say
             * which. The rate alone cannot tell them apart.
             */
            $table->string('tax_category', 2)->nullable()->after('tax_included');
        });

        // One row per series, locked while a number is taken. Gapless numbering
        // is a legal requirement in every regime here, and "max + 1" under
        // concurrency is how duplicates and holes appear.
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->string('series', 16)->primary();
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('transfer_id')->nullable()->constrained('transfers')->nullOnDelete();
            $table->foreignUlid('sale_return_id')->nullable()->constrained('sale_returns')->nullOnDelete();

            $table->string('series', 16);
            $table->unsignedInteger('sequence');
            $table->string('number', 64);

            // 388 standard invoice, 381 credit note, per UNCL 1001.
            $table->enum('type', ['standard', 'simplified', 'credit_note'])->default('simplified');

            $table->timestamp('issued_at');
            $table->string('currency_code', 3)->default('ILS');

            // Seller identity frozen at issue: the invoice must keep saying who
            // issued it even after the business is renamed or re-registered.
            $table->string('seller_name');
            $table->string('seller_tax_number', 64)->nullable();
            $table->string('seller_address')->nullable();

            $table->string('buyer_name')->nullable();
            $table->string('buyer_tax_number', 64)->nullable();
            $table->string('buyer_address')->nullable();

            // The five monetary totals EN 16931 requires, and their arithmetic
            // relationship is checked before the document is stored.
            $table->decimal('line_extension_amount', 14, 2)->default(0);
            $table->decimal('allowance_total', 14, 2)->default(0);
            $table->decimal('tax_exclusive_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('tax_inclusive_amount', 14, 2)->default(0);
            $table->decimal('payable_amount', 14, 2)->default(0);

            // Each document hashes the one before it. Editing an old invoice
            // breaks every hash after it, which is the point.
            $table->string('previous_hash', 128)->nullable();
            $table->string('hash', 128);

            // Base64 TLV, the payload a compliant QR carries.
            $table->text('qr_payload')->nullable();

            $table->longText('xml')->nullable();

            $table->foreignUlid('team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->unique(['series', 'sequence']);
            $table->unique('number');
            $table->index('issued_at');
            $table->index('transfer_id');
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignUlid('menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();

            $table->unsignedInteger('position')->default(1);
            $table->string('name');

            $table->decimal('quantity', 12, 3);

            // UN/CEFACT Rec 20 - mandatory on an EN 16931 line.
            $table->string('unit_code', 8)->default('H87');

            // Net of tax. The standard is unambiguous that line amounts exclude
            // tax, whatever the shelf price includes.
            $table->decimal('unit_price', 12, 3);
            $table->decimal('line_extension_amount', 14, 2);

            $table->string('tax_category', 2)->default('S');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);

            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_sequences');

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('tax_category');
        });
    }
};
