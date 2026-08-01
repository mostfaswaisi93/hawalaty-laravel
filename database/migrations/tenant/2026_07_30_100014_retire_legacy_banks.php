<?php

use App\Services\Tenant\LegacyBankMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('banks')) {
            return;
        }

        app(LegacyBankMigrator::class)->migrate();

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignUlid('account_id')->nullable()->after('bank_id')->constrained('accounts')->restrictOnDelete();
        });

        DB::connection('tenant')->statement(
            'UPDATE `expenses` e
             INNER JOIN `accounts` a ON a.`legacy_bank_id` = e.`bank_id`
             SET e.`account_id` = a.`id`
             WHERE e.`account_id` IS NULL'
        );

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->dropColumn('bank_id');
        });

        DB::connection('tenant')->statement('ALTER TABLE `expenses` MODIFY `account_id` CHAR(26) NOT NULL');

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->dropColumn('bank_id');
            $table->dropForeign(['change_bank_id']);
            $table->dropColumn('change_bank_id');
        });

        Schema::drop('banks');
    }

    public function down(): void
    {
    }
};
