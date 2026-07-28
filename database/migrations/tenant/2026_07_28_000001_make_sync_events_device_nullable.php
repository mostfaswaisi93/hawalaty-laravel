<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `sync_events.device_id` becomes nullable.
 *
 * SyncService::push() has always accepted a null device - the controller looks
 * the device up from the Sanctum token and finds nothing when the client never
 * registered one, or when its registration was revoked. The column did not allow
 * null, so every event in such a push failed on the insert and came back in
 * `failed[]` with a raw database message. The client had done nothing wrong and
 * had no way to interpret it.
 *
 * The column exists for attribution, not correctness: ordering comes from
 * `server_sequence` and idempotency from `client_uuid`, and neither needs a
 * device. Allowing null lets the push succeed and simply record that the origin
 * device is unknown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('sync_events', function (Blueprint $table) {
            $table->foreignUlid('device_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows written without a device cannot be given one retroactively, so
        // they are dropped rather than blocking the rollback on a null column.
        Schema::connection('tenant')->table('sync_events', function (Blueprint $table) {
            $table->foreignUlid('device_id')->nullable(false)->change();
        });
    }
};
