<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->enum('type', ['bank', 'wallet']);
            $table->string('code');
            $table->boolean('active')->default(true);
            // WhatsApp number for order notifications — see BACKEND_PROMPT.md section 9.
            $table->string('phone')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
