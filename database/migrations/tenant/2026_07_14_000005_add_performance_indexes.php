<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
