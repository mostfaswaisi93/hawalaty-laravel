<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight customer/recipient directory. `transfers.customer` (a plain
 * text snapshot of the name at order time) is kept as-is — customer_id is
 * an ADDITIVE, nullable link so existing orders and the API contract are
 * unaffected. Matching is by exact trimmed name (see CustomerService),
 * intentionally simple for v1: no dedupe-by-phone, no merge tooling.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->timestamps();

            $table->index('name');
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->foreignUlid('customer_id')->nullable()->after('customer')
                ->constrained('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::dropIfExists('customers');
    }
};
