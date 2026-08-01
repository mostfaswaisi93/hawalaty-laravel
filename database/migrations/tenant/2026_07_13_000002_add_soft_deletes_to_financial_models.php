<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft deletes on banks, expenses and transfers, so a mistaken delete is
 * recoverable rather than destroying the record and its line items.
 *
 * TransferLineItem is intentionally NOT included: it has no delete route of its
 * own and is only removed via its parent, which is now soft-deleted - so line
 * items simply stay in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
