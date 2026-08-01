<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
