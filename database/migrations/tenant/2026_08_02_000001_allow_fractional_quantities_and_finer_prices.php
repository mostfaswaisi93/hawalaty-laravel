<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quantities become fractional and unit prices gain a third decimal.
 *
 * `quantity` was an unsigned integer, which made four of the twelve verticals
 * impossible rather than merely awkward: 1.35 kg of produce, 22.7 L of fuel,
 * half a kilo of pastries and 1.5 hours of labour all round to nonsense.
 *
 * `price` was decimal(10,2). Fuel is priced per litre to three places by
 * convention everywhere it is sold, and a pharmacy splitting a 30-tablet strip
 * prices the tablet the same way. Rounding the unit price to two places pushes
 * the error into every line and it accumulates against the cash count. The line
 * *total* stays at two places - that is money, and money is two places.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_line_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
            $table->decimal('price', 12, 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('transfer_line_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->change();
            $table->decimal('price', 10, 2)->change();
        });
    }
};
