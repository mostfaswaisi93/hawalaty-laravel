<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * team_members.role_id previously cascade-deleted, so deleting a Role would
 * silently delete every team member (login account) holding it, cascading
 * further into devices/sync_events. RoleService::delete() already guards
 * this with a check-then-delete in the application layer, but that check is
 * non-atomic - the same class of gap the banks migration
 * (2026_07_13_000001_restrict_bank_deletion_on_financial_data) hardened at
 * the DB level for financial rows. restrictOnDelete() is the same
 * last-resort safety net here, for login accounts instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });
    }
};
