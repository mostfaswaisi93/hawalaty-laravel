<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * banks.code identifies a specific bank/wallet but had no uniqueness
 * guarantee at either the DB or validation layer, so two Bank rows could
 * share the same code and make code-based lookups/reporting ambiguous.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });
    }
};
