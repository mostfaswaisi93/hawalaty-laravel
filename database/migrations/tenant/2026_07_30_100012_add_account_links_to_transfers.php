<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->foreignUlid('account_id')->nullable()->after('bank_id')->constrained('accounts')->nullOnDelete();
            $table->foreignUlid('change_account_id')->nullable()->after('change_bank_id')->constrained('accounts')->nullOnDelete();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->ulid('legacy_bank_id')->nullable()->after('id');
            $table->index('legacy_bank_id');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
            $table->dropForeign(['change_account_id']);
            $table->dropColumn('change_account_id');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex(['legacy_bank_id']);
            $table->dropColumn('legacy_bank_id');
        });
    }
};
