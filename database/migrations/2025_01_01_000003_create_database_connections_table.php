<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_connections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('license_id')->unique()->constrained('licenses')->cascadeOnDelete();
            $table->string('host')->default('127.0.0.1');
            $table->unsignedInteger('port')->default(3306);
            $table->string('database_name');
            $table->string('username');

            $table->text('password');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_connections');
    }
};
