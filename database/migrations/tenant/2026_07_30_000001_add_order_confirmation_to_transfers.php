<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->string('confirmation_token', 64)->nullable()->unique()->after('transferred_amount');
            $table->timestamp('confirmed_at')->nullable()->after('confirmation_token');
            $table->enum('confirmation_action', ['confirmed', 'rejected'])->nullable()->after('confirmed_at');

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
