<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Price history.
 *
 * A margin report that only knows today's price and today's cost can say the
 * margin is thin. It cannot say why - whether the price was always wrong, or
 * whether the cost climbed underneath a price nobody revisited. That second case
 * is the common one and the expensive one, and answering it needs the cost as it
 * stood when the price was last set.
 *
 * So the cost is written alongside the price at every change. Joining to the
 * item's current cost later would answer a different question.
 *
 * This starts recording from the day it is deployed. There is no way to
 * reconstruct history that was never kept, and pretending otherwise by
 * back-filling today's cost onto old rows would produce a report that looks
 * informative and is not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_changes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('menu_item_id')->constrained('menu_items')->cascadeOnDelete();

            // Null on the first row: there was no price before the first price.
            $table->decimal('old_price', 12, 3)->nullable();
            $table->decimal('new_price', 12, 3);

            // The weighted average at the moment of the change - the baseline the
            // margin was chosen against.
            $table->decimal('cost_at_change', 12, 3)->default(0);

            $table->foreignUlid('team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['menu_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_changes');
    }
};
