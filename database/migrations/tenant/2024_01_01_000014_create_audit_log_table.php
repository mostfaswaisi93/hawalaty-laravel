<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('actor_name');
            $table->string('actor_email');
            $table->enum('action', [
                'created', 'updated', 'deleted', 'signedOut', 'loggedIn',
            ]);
            $table->enum('entity_type', [
                'expense', 'bank', 'menuItem', 'constant', 'device',
                'order', 'teamMember', 'role',
            ]);
            $table->string('entity_label');
            $table->enum('category', [
                'auth', 'team', 'banks', 'menu',
                'expenses', 'constants', 'devices', 'transfers',
            ]);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
