<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The orders/transfers table — the heart of the system.
     * See BACKEND_PROMPT.md section 4 and D:\Angular\Hawalaty\src\app\data\transfers.ts.
     */
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('customer');
            $table->enum('status', ['completed', 'pending', 'failed'])->default('pending');
            $table->decimal('amount', 10, 2);
            // Pre-formatted text summary of the line items, as in the current UI.
            $table->text('item_name')->nullable();

            $table->enum('payment_method', ['cash', 'bank']);

            // Cash payment scenario.
            $table->boolean('has_change')->nullable();
            $table->enum('change_method', ['cash', 'bank'])->nullable();
            $table->foreignUlid('change_bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->decimal('change_amount', 10, 2)->nullable();

            // Bank transfer / wallet scenario.
            $table->foreignUlid('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->string('sender_name')->nullable();
            $table->string('sender_phone')->nullable();
            $table->decimal('transferred_amount', 10, 2)->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
