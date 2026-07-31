<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2a - reference catalogs.
 *
 * The catalogue of wallet PROVIDER names (جوّال باي، ريفلكت...). A wallet
 * Account (Phase 2b) references one via `wallet_name_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_names', function (Blueprint $table) {
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
        Schema::dropIfExists('wallet_names');
    }
};
