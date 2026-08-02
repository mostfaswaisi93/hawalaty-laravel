<?php

use App\Enums\BusinessType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The business vertical is platform-owned: the operator picks it when creating the
 * license and the tenant cannot change it, because it decides which modules and
 * database structures that workspace was provisioned with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->string('business_type', 32)
                ->default(BusinessType::default()->value)
                ->after('phone');
        });

        // Existing licenses predate verticals; park them on the generic retail one.
        DB::table('licenses')
            ->whereNull('business_type')
            ->update(['business_type' => BusinessType::default()->value]);

        Schema::table('licenses', function (Blueprint $table) {
            $table->index('business_type');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropIndex(['business_type']);
            $table->dropColumn('business_type');
        });
    }
};
