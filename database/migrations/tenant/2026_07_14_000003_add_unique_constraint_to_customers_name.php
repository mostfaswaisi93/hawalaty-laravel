<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CustomerService::findOrCreate() does a plain check-then-insert with no
 * locking - under concurrent orders for the same new customer name, two
 * requests can both see "not found" and each insert their own row,
 * permanently splitting that customer's order_count/total_spent. A unique
 * constraint turns the losing concurrent insert into a catchable exception
 * instead of a silent duplicate; CustomerService::findOrCreate() is updated
 * alongside this migration to catch it and re-fetch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->index('name');
        });
    }
};
