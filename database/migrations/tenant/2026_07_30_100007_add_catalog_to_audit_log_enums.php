<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2a - lets catalog writes be audited.
 *
 * CatalogService records an audit row with entity_type `catalog` / category
 * `catalogs` (see the matching cases added to App\Enums\AuditEntityType and
 * App\Enums\AuditCategory). Those columns are MySQL ENUMs with a fixed value
 * list, so the new members have to be added to the column definition or every
 * insert is truncated to '' and rejected.
 *
 * Raw SQL, not ->change(): this project has no doctrine/dbal (same reason as
 * 2024_01_01_000017_make_sync_events_device_id_nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE audit_log MODIFY entity_type ENUM('expense','bank','menuItem','constant','device','order','teamMember','role','catalog') NOT NULL");
        DB::statement("ALTER TABLE audit_log MODIFY category ENUM('auth','team','banks','menu','expenses','constants','devices','transfers','catalogs') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE audit_log MODIFY entity_type ENUM('expense','bank','menuItem','constant','device','order','teamMember','role') NOT NULL");
        DB::statement("ALTER TABLE audit_log MODIFY category ENUM('auth','team','banks','menu','expenses','constants','devices','transfers') NOT NULL");
    }
};
