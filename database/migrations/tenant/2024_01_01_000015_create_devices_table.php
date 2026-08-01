<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_member_id')->constrained('team_members')->cascadeOnDelete();

            $table->foreignId('sanctum_token_id')->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->string('name');
            $table->string('browser');
            $table->string('platform_icon');
            $table->timestamp('last_active_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
