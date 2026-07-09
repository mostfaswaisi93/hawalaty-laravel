<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lives inside EVERY tenant database (physical DB-per-tenant, see
     * BACKEND_PROMPT.md section 4) — unlike the superseded single-shared-DB
     * prototype where this was one global catalog, each tenant now gets its
     * own copy of the 7 fixed rows (App\Enums\PermissionKey), seeded during
     * provisioning.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
