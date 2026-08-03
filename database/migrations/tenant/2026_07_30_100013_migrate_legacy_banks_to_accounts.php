<?php

use App\Services\Tenant\LegacyBankMigrator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(LegacyBankMigrator::class)->migrate();
    }

    public function down(): void {}
};
