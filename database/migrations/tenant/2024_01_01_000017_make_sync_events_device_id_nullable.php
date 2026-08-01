<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
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
