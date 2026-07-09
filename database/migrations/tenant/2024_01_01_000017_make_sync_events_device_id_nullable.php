<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fixes a real bug found during this task's live verification:
     * `sync_events.device_id` was NOT NULL, but this codebase's route list
     * (BACKEND_PROMPT.md) has no "register a device" endpoint at all — only
     * GET /devices and DELETE /devices/{id}. AuthController::login() (out
     * of this task's scope, already built/verified) never creates a Device
     * row either. That means any team member who pushes a sync event
     * before a Device row exists for their current token would always hit
     * an integrity-constraint violation — reproduced with a real curl
     * POST /api/v1/sync/push during this task's verification pass.
     * `device_id` nullable here + App\Services\Tenant\SyncService already
     * passing `$device?->id` is the pragmatic fix within this task's scope;
     * wiring actual device registration is future work.
     *
     * Raw SQL rather than Schema::table(...)->change() — this project has
     * no doctrine/dbal dependency, which ->change() requires.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE sync_events DROP FOREIGN KEY sync_events_device_id_foreign');
        DB::statement('ALTER TABLE sync_events MODIFY device_id CHAR(26) NULL');
        DB::statement('ALTER TABLE sync_events ADD CONSTRAINT sync_events_device_id_foreign FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sync_events DROP FOREIGN KEY sync_events_device_id_foreign');
        DB::statement('ALTER TABLE sync_events MODIFY device_id CHAR(26) NOT NULL');
        DB::statement('ALTER TABLE sync_events ADD CONSTRAINT sync_events_device_id_foreign FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE');
    }
};
