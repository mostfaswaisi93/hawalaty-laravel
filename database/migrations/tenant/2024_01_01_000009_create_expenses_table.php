<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A tenant's OWN business expenses — distinct from the platform's own
     * operating expenses (App\Models\Expense, platform `expenses` table).
     * See App\Models\Tenant\Expense.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->foreignUlid('bank_id')->constrained('banks')->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
