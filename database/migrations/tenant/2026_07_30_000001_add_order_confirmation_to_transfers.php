<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bank-owner confirmation of a transfer.
     *
     * A bank/wallet order carries an unguessable `confirmation_token`. It is
     * embedded (as a public link) in the WhatsApp message sent to the bank
     * account owner, who opens it to confirm the money arrived or report that
     * it did not - without any login. `confirmed_at` + `confirmation_action`
     * record their decision and make the link idempotent: a second visit shows
     * "already confirmed" instead of acting twice.
     *
     * `created_by` is the team member who raised the order, captured so the
     * confirmation can notify exactly that person (not the whole team) once the
     * bank owner responds.
     */
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            // Nullable + unique: cash orders and every pre-existing row stay
            // NULL, and MySQL permits many NULLs under a unique index.
            $table->string('confirmation_token', 64)->nullable()->unique()->after('transferred_amount');
            $table->timestamp('confirmed_at')->nullable()->after('confirmation_token');
            $table->enum('confirmation_action', ['confirmed', 'rejected'])->nullable()->after('confirmed_at');

            // nullOnDelete so removing a team member never blocks or orphans an
            // order; the notification target simply becomes "no one".
            $table->foreignUlid('created_by')->nullable()->after('confirmation_action')
                ->constrained('team_members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['confirmation_token', 'confirmed_at', 'confirmation_action', 'created_by']);
        });
    }
};
