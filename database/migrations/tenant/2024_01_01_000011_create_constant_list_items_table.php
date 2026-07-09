<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('constant_list_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('constant_list_id')->constrained('constant_lists')->cascadeOnDelete();
            $table->string('value');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('constant_list_items');
    }
};
