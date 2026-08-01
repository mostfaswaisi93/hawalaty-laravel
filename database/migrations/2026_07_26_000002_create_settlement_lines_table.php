<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('settlement_id')->constrained('settlements')->cascadeOnDelete();
            $table->foreignUlid('partner_id')->nullable()->constrained('partners')->nullOnDelete();

            $table->string('partner_name');
            $table->decimal('percentage', 5, 2);

            $table->decimal('entitlement', 12, 2);

            $table->decimal('drawn', 12, 2);

            $table->decimal('net', 12, 2);

            $table->timestamps();

            $table->index(['settlement_id', 'partner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_lines');
    }
};
