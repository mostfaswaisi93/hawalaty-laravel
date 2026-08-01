<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->enum('kind', ['bank', 'wallet']);

            $table->foreignUlid('bank_name_id')->nullable()->constrained('bank_names')->restrictOnDelete();
            $table->foreignUlid('account_type_id')->nullable()->constrained('account_types')->restrictOnDelete();
            $table->string('iban')->nullable();

            $table->foreignUlid('wallet_name_id')->nullable()->constrained('wallet_names')->restrictOnDelete();
            $table->foreignUlid('wallet_type_id')->nullable()->constrained('wallet_types')->restrictOnDelete();
            $table->string('wallet_phone')->nullable();

            $table->foreignUlid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('holder_name');
            $table->string('phone')->nullable();

            $table->string('whatsapp_phone')->nullable();
            $table->decimal('balance', 15, 2)->default(0);
            $table->boolean('active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
