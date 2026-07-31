<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shop's own address and phone, as printed on its receipts.
 *
 * They live on the LICENCE rather than in the tenant's own settings because
 * they identify the business the licence was sold to - the same place
 * `customer_name` already lives, and the same place the operator fills in when
 * provisioning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->string('address')->nullable()->after('customer_name');
            $table->string('phone', 50)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn(['address', 'phone']);
        });
    }
};
