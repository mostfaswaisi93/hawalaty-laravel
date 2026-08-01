<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_currency', function (Blueprint $table) {
            $table->ulid('catalog_id');
            $table->string('catalog_type');
            $table->foreignUlid('currency_id')->constrained('currencies')->cascadeOnDelete();

            $table->index(['catalog_type', 'catalog_id']);
            $table->unique(['catalog_type', 'catalog_id', 'currency_id'], 'catalog_currency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_currency');
    }
};
