<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A team member's agreed pay rate.
 *
 * Nullable on purpose: not everyone on the team is salaried, and the page must
 * keep working for tenants who never fill these in. The rate is only a DEFAULT
 * for the payment form - what actually hits the books is the amount recorded on
 * each payment, so raising someone's salary never rewrites their history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->decimal('salary_amount', 10, 2)->nullable()->after('active');
            $table->string('salary_period')->nullable()->after('salary_amount');
        });
    }

    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn(['salary_amount', 'salary_period']);
        });
    }
};
