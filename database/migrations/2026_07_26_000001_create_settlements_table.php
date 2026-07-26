<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A clearing run (مقاصة): the platform closes a period, works out what each
 * partner earned against what they already withdrew, and freezes the result.
 *
 * Every money figure is SNAPSHOT here rather than recomputed on read. A
 * settlement is an accounting document - once confirmed it must keep showing
 * the numbers it was confirmed with, even if a partner's percentage is edited
 * or a box is renamed afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Human-facing document number, e.g. MQ-2026-0001. Unique so two
            // concurrent runs can never claim the same reference.
            $table->string('reference')->unique();

            // Inclusive period the run covers. `period_from` is the day after
            // the previous confirmed run's `period_to`, so periods tile without
            // gaps or overlaps and no movement is ever counted twice.
            $table->date('period_from');
            $table->date('period_to');

            // Period totals, all snapshots.
            $table->decimal('total_payments', 12, 2);
            $table->decimal('total_expenses', 12, 2);
            // payments - expenses. Withdrawals are NOT subtracted here: they
            // are distributions OF this profit, not a cost of earning it.
            $table->decimal('distributable_profit', 12, 2);
            $table->decimal('total_entitlement', 12, 2);
            $table->decimal('total_drawn', 12, 2);
            $table->decimal('total_net', 12, 2);
            // The slice left with the business when partner percentages sum to
            // less than 100.
            $table->decimal('retained', 12, 2);

            $table->string('status')->default('draft');

            $table->foreignUlid('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            // Serves both the list page (latest first) and the "find the last
            // confirmed run" lookup that decides the next period's start.
            $table->index(['status', 'period_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
