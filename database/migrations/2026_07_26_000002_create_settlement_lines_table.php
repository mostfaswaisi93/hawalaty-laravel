<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One partner's position inside a clearing run.
 *
 * `partner_name` and `percentage` are deliberately duplicated from `partners`
 * rather than joined at read time: a confirmed settlement is a statement the
 * partner was shown, so editing their share afterwards must not silently
 * rewrite history. The FK is kept for navigation and is nulled (not cascaded)
 * if the partner row is ever removed, so the document survives intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('settlement_id')->constrained('settlements')->cascadeOnDelete();
            $table->foreignUlid('partner_id')->nullable()->constrained('partners')->nullOnDelete();

            $table->string('partner_name');
            $table->decimal('percentage', 5, 2);

            // distributable_profit * percentage / 100
            $table->decimal('entitlement', 12, 2);
            // What this partner actually withdrew inside the period.
            $table->decimal('drawn', 12, 2);
            // entitlement - drawn. Positive: the platform still owes them.
            // Negative: they over-drew and carry a debit into the next period.
            $table->decimal('net', 12, 2);

            $table->timestamps();

            $table->index(['settlement_id', 'partner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_lines');
    }
};
