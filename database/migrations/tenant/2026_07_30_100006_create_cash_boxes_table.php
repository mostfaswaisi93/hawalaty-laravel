<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2a - reference catalogs.
 *
 * The business's own cash boxes / drawers (صندوق المحل، صندوق الفرع...). Cash
 * transactions post against one of these. Kept as a plain named catalog here;
 * its accounting ledger is wired up alongside the Account ledger in Phase 2b.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_boxes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_boxes');
    }
};
