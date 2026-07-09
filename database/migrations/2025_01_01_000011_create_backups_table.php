<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // null = a backup of the platform DB itself, not any single tenant.
            $table->foreignUlid('license_id')->nullable()->constrained('licenses')->nullOnDelete();
            $table->unsignedInteger('size_mb')->nullable();
            $table->enum('trigger', ['manual', 'scheduled']);
            $table->string('actor_name')->nullable();
            $table->enum('status', ['completed', 'failed', 'inProgress']);
            $table->string('file_path')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
