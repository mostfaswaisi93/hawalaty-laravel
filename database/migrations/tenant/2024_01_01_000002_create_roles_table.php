<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Null for custom (non-system) roles; a system key for the 3
            // built-in roles cloned into every tenant at provisioning time.
            $table->enum('key', ['owner', 'manager', 'staff'])->nullable();
            $table->string('name');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
