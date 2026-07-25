<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds soft deletes to the three financial record types that previously had
 * NO protection against accidental permanent deletion: banks (already
 * app-level guarded against deletion while it has history - see
 * BankService::delete() - but a history-free bank was still permanently
 * gone if deleted by mistake), expenses, and transfers (both had zero
 * safeguards: one click on "delete" destroyed the record, and for a
 * transfer, cascade-deleted its line items too, with nothing recoverable).
 *
 * TransferLineItem is intentionally NOT included: it has no direct delete
 * route of its own (only ever removed via its parent Transfer, which is now
 * itself soft-deleted - so line items simply stay in the database,
 * untouched, when their parent transfer is soft-deleted).
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
