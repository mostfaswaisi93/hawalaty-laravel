<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A salary actually paid out.
 *
 * Every row owns exactly one `expenses` row - paying a salary IS money leaving a
 * bank or wallet, so it must appear in المصروفات and in the reports without any
 * special-casing. This table adds only what an expense cannot express: WHO was
 * paid, and for which period.
 *
 * `expense_id` cascades: deleting the expense (the money movement) must not
 * leave an orphan payment claiming someone was paid. The account the money left
 * from is NOT duplicated here - it lives on the expense's `bank_id`, so the two
 * can never disagree.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_member_id')->constrained('team_members')->cascadeOnDelete();
            $table->foreignUlid('expense_id')->constrained('expenses')->cascadeOnDelete();

            // Snapshot of what was paid and on what basis, so a later change to
            // the member's rate cannot rewrite an already-settled payment.
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
