<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's standard database-notifications table (exact shape the
 * framework's Illuminate\Notifications\DatabaseNotification model expects —
 * uuid primary key via HasUuids, not this app's usual ulid(), since that
 * model isn't swappable without extra config; every other tenant table
 * stays ULID as normal). Backs the bell-icon notification center in
 * layouts/app.blade.php for TeamMember (already Notifiable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuidMorphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
