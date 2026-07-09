<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('license_code')->unique();
            $table->string('customer_name');
            $table->foreignUlid('package_id')->constrained('packages');
            $table->enum('status', ['trial', 'active', 'suspended', 'expired'])->default('trial');
            $table->date('expired_at');
            $table->decimal('customer_balance', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
