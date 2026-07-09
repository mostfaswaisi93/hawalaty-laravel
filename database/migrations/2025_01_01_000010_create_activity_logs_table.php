<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-level audit trail of admin actions (license created, plan
     * changed, payment recorded...). Fully separate from `audit_log`, which
     * lives inside each tenant's own database.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('action');
            $table->string('entity_type');
            $table->string('entity_label');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
