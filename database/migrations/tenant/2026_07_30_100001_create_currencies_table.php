<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2a - reference catalogs for the accounting redesign.
 *
 * The currencies each account holds its money in. `symbol` (₪ / $ / €) is what
 * money is formatted with, replacing the hard-coded " ₪" scattered through the
 * old single-currency views.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('symbol', 8);
            $table->boolean('active')->default(true);
            $table->timestamps();
            // Soft deletes because an account (Phase 2b) will point at a
            // currency; retiring one must not break historical accounts that
            // still resolve its name and symbol.
            $table->softDeletes();

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
