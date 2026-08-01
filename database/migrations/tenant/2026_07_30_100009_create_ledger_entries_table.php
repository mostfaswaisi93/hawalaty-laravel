<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

            $table->index(['account_id', 'id']);
        });

        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_debit_non_negative CHECK (debit >= 0)');
        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_credit_non_negative CHECK (credit >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
