<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composition and choice: what a dish is made of, and what the customer may
 * change about it.
 *
 * A restaurant that sells a burger does not hold burgers - it holds bread, meat
 * and cheese. Without a bill of materials the stock module is decorative there:
 * nothing ever depletes, because nothing was ever counted as a burger.
 *
 * Two shapes are supported and the item's own `track_stock` flag decides which:
 *
 *   - Assemble to order (a restaurant): the finished item is not stocked, so
 *     selling it consumes its components straight from the shelf.
 *   - Produce to stock (a bakery): the finished item IS stocked. A production
 *     run consumes the components and adds finished goods, and sales then
 *     deplete those goods normally.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_components', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('parent_item_id')->constrained('menu_items')->cascadeOnDelete();

            // Restricted, not cascaded: deleting an ingredient that dishes still
            // depend on would silently make their cost and depletion wrong.
            $table->foreignUlid('component_item_id')->constrained('menu_items')->restrictOnDelete();

            // Four decimals because recipes are the one place three is not
            // enough - 0.0025 kg of saffron is a real line in a real recipe.
            $table->decimal('quantity', 12, 4);

            // Trim, peel and evaporation. Costing that ignores yield loss
            // understates every dish that involves a knife.
            $table->decimal('waste_percent', 5, 2)->default(0);

            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['parent_item_id', 'component_item_id']);
            $table->index('component_item_id');
        });

        Schema::create('production_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('menu_item_id')->constrained('menu_items')->restrictOnDelete();
            $table->foreignUlid('location_id')->constrained('locations')->restrictOnDelete();

            $table->decimal('quantity', 12, 3);

            // draft -> posted is one way, like a purchase receipt: posting has
            // already moved stock in both directions.
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');
            $table->timestamp('produced_at')->nullable();

            // What the batch actually cost to make, frozen at posting.
            $table->decimal('unit_cost', 12, 3)->default(0);

            // A bakery's output has a shelf life of hours; the run opens the lot.
            $table->string('batch_number', 64)->nullable();
            $table->date('expires_at')->nullable();

            $table->text('notes')->nullable();
            $table->foreignUlid('team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'produced_at']);
        });

        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');

            // single = pick one (size), multiple = pick any (toppings).
            $table->enum('selection', ['single', 'multiple'])->default('single');

            $table->boolean('is_required')->default(false);
            $table->unsignedTinyInteger('min_select')->default(0);
            $table->unsignedTinyInteger('max_select')->nullable();

            $table->unsignedInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('modifiers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('modifier_group_id')->constrained('modifier_groups')->cascadeOnDelete();

            $table->string('name');

            // Signed: "no onion" may be zero, "extra cheese" positive, and a
            // smaller size negative.
            $table->decimal('price_delta', 12, 3)->default(0);

            // Extra cheese is extra cheese off the shelf. Optional, because most
            // options cost nothing to stock.
            $table->foreignUlid('component_item_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->decimal('component_quantity', 12, 4)->nullable();

            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['modifier_group_id', 'name']);
        });

        // Groups are shared: one "size" group serves every drink on the menu.
        Schema::create('menu_item_modifier_group', function (Blueprint $table) {
            $table->foreignUlid('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignUlid('modifier_group_id')->constrained('modifier_groups')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);

            $table->primary(['menu_item_id', 'modifier_group_id'], 'menu_item_modifier_group_primary');
        });

        Schema::create('transfer_line_item_modifiers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('transfer_line_item_id')->constrained('transfer_line_items')->cascadeOnDelete();

            // Nulls out rather than cascades: a modifier removed from the menu
            // next month must not rewrite what an old receipt said.
            $table->foreignUlid('modifier_id')->nullable()->constrained('modifiers')->nullOnDelete();

            $table->string('name');
            $table->decimal('price_delta', 12, 3)->default(0);
            $table->timestamps();

            $table->index('transfer_line_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_line_item_modifiers');
        Schema::dropIfExists('menu_item_modifier_group');
        Schema::dropIfExists('modifiers');
        Schema::dropIfExists('modifier_groups');
        Schema::dropIfExists('production_runs');
        Schema::dropIfExists('recipe_components');
    }
};
