<?php

use App\Services\Tenant\LegacyBankMigrator;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 2c (data half) - migrate the legacy `banks` into accounts and repoint
 * orders. The work lives in LegacyBankMigrator (so it can be tested against
 * seeded data); this migration just runs it. No-op on a fresh database with no
 * legacy banks, so it is safe in the test harness's migrate:fresh.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(LegacyBankMigrator::class)->migrate();
    }

    public function down(): void
    {
        // A data migration is not automatically reversible: the migrated
        // accounts may since have grown ledgers and orders of their own. The
        // schema half's down() removes the columns; the accounts are left.
    }
};
