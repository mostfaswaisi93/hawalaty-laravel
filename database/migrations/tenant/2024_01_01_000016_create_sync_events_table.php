<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The offline-sync engine's event log — see BACKEND_PROMPT.md sections 9
     * and 10. `server_sequence` is deliberately NOT a ULID like every other
     * table here: it's an auto-incrementing integer that doubles as the
     * strictly-ordered cursor devices pass to the delta-pull endpoint, which
     * a ULID (random-ish sortable, not a dense integer sequence) cannot
     * serve as. `entity_id` stores the ULID of the affected row.
     */
    public function up(): void
    {
        Schema::create('sync_events', function (Blueprint $table) {
            $table->id('server_sequence');
            $table->foreignUlid('device_id')->constrained('devices')->cascadeOnDelete();
            // Prevents duplicate application if a device resends the same
            // event after a flaky connection (idempotency).
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
