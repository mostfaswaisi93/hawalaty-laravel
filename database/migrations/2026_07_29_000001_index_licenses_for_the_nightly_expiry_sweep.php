<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `licenses:expire-overdue` runs every night and asks the same question every
 * time: which trial/active licences are past their date? That was a full table
 * scan - `licenses` carried no index beyond the primary key and the unique
 * `license_code`.
 *
 * Composite and in this order because the query filters an equality set on
 * `status` first and a range on `expired_at` second; a range column can only be
 * the LAST useful part of an index, so (expired_at, status) would stop being
 * usable at the first column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->index(['status', 'expired_at'], 'licenses_status_expired_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropIndex('licenses_status_expired_at_index');
        });
    }
};
