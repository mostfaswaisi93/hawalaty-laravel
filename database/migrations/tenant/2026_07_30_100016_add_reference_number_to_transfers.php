<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The bank/wallet transaction's own reference number, as printed on the
 * operator's transfer slip. It is what an order is reconciled against, so it
 * must be UNIQUE: entering the same reference twice means the same transfer was
 * booked twice.
 *
 * Nullable, because a cash order has no reference. MySQL allows any number of
 * NULLs in a unique index, so cash orders never collide with each other while
 * two real references still cannot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->string('reference_number', 100)->nullable()->after('transferred_amount');
            $table->unique('reference_number');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropUnique(['reference_number']);
            $table->dropColumn('reference_number');
        });
    }
};
