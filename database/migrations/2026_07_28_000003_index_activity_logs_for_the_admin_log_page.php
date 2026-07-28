<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The platform's own `activity_logs`, with the same gap as the tenant's
 * `audit_logs` - see that migration.
 *
 * Append-only, read only in `created_at DESC` order (the admin log page, its
 * API twin, and the dashboard's "latest 8"), and indexed on nothing but the
 * `admin_id` foreign key. The admin filter narrows by `admin_id` and still
 * sorts by date, so the composite carries both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('created_at', 'activity_logs_created_at_index');
            $table->index(['admin_id', 'created_at'], 'activity_logs_admin_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('activity_logs_created_at_index');
            $table->dropIndex('activity_logs_admin_created_at_index');
        });
    }
};
