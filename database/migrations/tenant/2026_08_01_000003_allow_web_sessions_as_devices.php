<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a browser session appear on /devices alongside API tokens.
 *
 * A Device was tied to a Sanctum token, which only the mobile/API login issues -
 * so someone working entirely in the browser saw an empty page and no way to
 * spot or end a session on a machine they no longer control.
 *
 * `sanctum_token_id` becomes nullable and `session_id` is added; exactly one of
 * the two identifies a device. `session_id` is unique so a returning browser
 * updates its row rather than adding one on every page load, and it is a plain
 * string because Laravel's session id lives in the PLATFORM database while this
 * table is per-tenant - a foreign key cannot span the two.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('devices', function (Blueprint $table) {
            $table->dropForeign(['sanctum_token_id']);
        });

        Schema::connection('tenant')->table('devices', function (Blueprint $table) {
            $table->unsignedBigInteger('sanctum_token_id')->nullable()->change();
            $table->string('session_id')->nullable()->unique()->after('sanctum_token_id');

            $table->foreign('sanctum_token_id')
                ->references('id')->on('personal_access_tokens')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('devices', function (Blueprint $table) {
            $table->dropForeign(['sanctum_token_id']);
            $table->dropUnique(['session_id']);
            $table->dropColumn('session_id');
        });

        Schema::connection('tenant')->table('devices', function (Blueprint $table) {
            $table->unsignedBigInteger('sanctum_token_id')->nullable(false)->change();
            $table->foreign('sanctum_token_id')
                ->references('id')->on('personal_access_tokens')
                ->cascadeOnDelete();
        });
    }
};
