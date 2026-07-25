<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sanctum's token table, one independent copy per tenant database - see
     * BACKEND_PROMPT.md section 8.1 and App\Models\TenantPersonalAccessToken.
     *
     * CRITICAL: ulidMorphs('tokenable'), NOT morphs('tokenable') - every
     * tokenable model in tenant space (TeamMember) uses HasUlids string
     * primary keys; plain morphs() would create an incompatible bigint
     * tokenable_id column that truncates ULIDs and breaks every token
     * insert. The token row's own primary key stays a normal auto-increment
     * id - Sanctum's PersonalAccessToken model is not ULID-keyed itself,
     * only the tokenable relation is (mirrors the platform DB's copy of
     * this same migration).
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->ulidMorphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
