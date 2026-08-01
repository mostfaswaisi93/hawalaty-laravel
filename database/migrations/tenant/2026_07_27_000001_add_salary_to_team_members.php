<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
