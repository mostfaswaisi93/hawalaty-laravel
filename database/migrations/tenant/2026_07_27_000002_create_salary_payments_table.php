<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_member_id')->constrained('team_members')->cascadeOnDelete();
            $table->foreignUlid('expense_id')->constrained('expenses')->cascadeOnDelete();

            $table->decimal('amount', 10, 2);
            $table->string('period');
            $table->date('paid_for');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['team_member_id', 'paid_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
    }
};
