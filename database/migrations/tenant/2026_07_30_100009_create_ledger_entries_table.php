<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2b - the accounting ledger (دفتر الأستاذ). One append-only row per
 * movement on an account: مدين (debit) / دائن (credit), the running balance
 * AFTER the movement, and a description. `running_balance` and the account's
 * own balance are written together under a row lock (AccountService::postEntry),
 * so the ledger and the stored balance never drift.
 *
 * Append-only: created_at only, no updated_at - a mistake is corrected with a
 * reversing entry, never by editing history (mirrors audit_log). debit/credit
 * are each non-negative (CHECK); running_balance is signed.
 *
 * `transfer_id` links an entry back to the order that produced it - null for
 * manual entries, filled when orders post to the ledger in Phase 2d.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->decimal('debit', 10, 2)->default(0);
            $table->decimal('credit', 10, 2)->default(0);
            $table->decimal('running_balance', 15, 2);
            $table->string('description')->nullable();
            $table->foreignUlid('transfer_id')->nullable()->constrained('transfers')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // Statement queries read one account's entries newest-first; ULID
            // ids are time-ordered, so (account_id, id) also fixes chronology.
            $table->index(['account_id', 'id']);
        });

        // Last-resort guards, same class as the monetary CHECK constraints on
        // transfers/expenses. MySQL 8.0.16+ enforces these.
        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_debit_non_negative CHECK (debit >= 0)');
        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_credit_non_negative CHECK (credit >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
