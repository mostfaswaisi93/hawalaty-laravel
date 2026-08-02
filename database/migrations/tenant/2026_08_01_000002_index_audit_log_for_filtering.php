<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the activity log's filters.
 *
 * The page always sorts by `created_at DESC` and filters by `category` and/or
 * `action`. MySQL uses ONE index per table reference, so the existing
 * single-column indexes let it filter OR sort, never both - the rest is a
 * filesort. Leading with the filtered column and trailing with the sort column
 * lets one index serve the whole query.
 *
 * The standalone `created_at` index stays: it is what the unfiltered page uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('audit_log', function (Blueprint $table) {
            $table->index(['category', 'created_at'], 'audit_log_category_created_at_index');
            $table->index(['action', 'created_at'], 'audit_log_action_created_at_index');
        });

        Schema::connection('tenant')->table('audit_log', function (Blueprint $table) {
            $table->dropIndex(['category']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('audit_log', function (Blueprint $table) {
            $table->index('category');
        });

        Schema::connection('tenant')->table('audit_log', function (Blueprint $table) {
            $table->dropIndex('audit_log_category_created_at_index');
            $table->dropIndex('audit_log_action_created_at_index');
        });
    }
};
