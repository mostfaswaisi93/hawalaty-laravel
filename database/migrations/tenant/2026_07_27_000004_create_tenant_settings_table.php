<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant configuration.
 *
 * The platform has its own `settings` table for platform-wide values; this is
 * the tenant-side equivalent, living inside each tenant's own database so one
 * business's wording can never leak into another's.
 *
 * It exists because the app hard-coded itself as a RESTAURANT - "قائمة الطعام",
 * "الأصناف" - while the same software is meant to run a fruit shop or a fuel
 * station just as well. What the catalogue is called has to be the tenant's
 * choice, so it needs somewhere to be stored.
 *
 * Deliberately key/value rather than a column per setting: the set of things a
 * tenant can name will grow, and a schema change per label would be absurd.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
