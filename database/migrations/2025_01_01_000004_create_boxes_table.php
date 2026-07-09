<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boxes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('box_name');
            $table->decimal('box_balance', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boxes');
    }
};
