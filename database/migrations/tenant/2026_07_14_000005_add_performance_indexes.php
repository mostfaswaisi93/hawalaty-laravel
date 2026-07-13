<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * audit_log is append-only and grows unboundedly, but ActivityLogController
 * filters it by category and sorts by created_at with zero supporting
 * index; transfers is filtered by date range (History, Reports) with only
 * a `status` index; expenses is sorted by `date` with none at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_log', function (Blueprint $table) {
            $table->index('category');
            $table->index('created_at');
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::table('audit_log', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['date']);
        });
    }
};
