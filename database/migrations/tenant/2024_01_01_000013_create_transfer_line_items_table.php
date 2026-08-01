<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_line_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('transfer_id')->constrained('transfers')->cascadeOnDelete();

            $table->foreignUlid('menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();

            $table->string('name');
            $table->unsignedInteger('quantity');

            $table->decimal('price', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_line_items');
    }
};
