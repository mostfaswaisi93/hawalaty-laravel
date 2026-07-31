<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2a - reference catalogs.
 *
 * The catalogue of bank INSTITUTION names (بنك فلسطين، البنك العربي...). Kept
 * separate from the legacy `banks` table (a payment method carrying a code +
 * phone, retired in Phase 2c): here a bank is only a name the account form
 * offers. A bank Account (Phase 2b) references one via `bank_name_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_names', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_names');
    }
};
