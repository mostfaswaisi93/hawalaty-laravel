<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_events', function (Blueprint $table) {
            $table->id('server_sequence');
            $table->foreignUlid('device_id')->constrained('devices')->cascadeOnDelete();

            $table->string('client_uuid')->unique();
            $table->string('entity_type');
            $table->string('entity_id');
            $table->enum('operation', ['create', 'update', 'delete']);
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_events');
    }
};
