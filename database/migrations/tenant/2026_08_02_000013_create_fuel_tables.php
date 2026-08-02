<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wet stock.
 *
 * A filling station does not count its inventory, it measures it - and the two
 * measurements never quite agree. Sales are the difference between two pump
 * readings; stock is what a dip or a gauge says is in the tank. Reconciling them
 * is the module, and the gap is the point: a persistent negative variance is how
 * a leak or a theft is found, and finding it is an environmental and legal duty
 * rather than a management preference.
 *
 * Standards behind the shapes here: OIML R117 for the meters themselves,
 * OIML R118 for their testing, and ASTM D1250 / API MPMS for correcting volume
 * to a reference temperature - which is why a delivery received cold and sold
 * warm produces a variance that is not a loss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_tanks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('menu_item_id')->constrained('menu_items')->restrictOnDelete();
            $table->foreignUlid('location_id')->nullable()->constrained('locations')->nullOnDelete();

            $table->string('name');
            $table->decimal('capacity', 12, 3)->default(0);

            // Below this the pumps start drawing sediment; it is a safety floor,
            // not a reorder point.
            $table->decimal('minimum_level', 12, 3)->default(0);

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('fuel_pumps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('fuel_tank_id')->constrained('fuel_tanks')->cascadeOnDelete();

            $table->string('name');

            // The seal on a legal-for-trade meter. Breaking it voids the
            // calibration, so the number is worth holding.
            $table->string('seal_number', 64)->nullable();
            $table->date('calibrated_at')->nullable();

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('fuel_shifts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->date('shift_date');
            $table->string('name')->nullable();

            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->foreignUlid('team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['shift_date', 'status']);
        });

        Schema::create('fuel_meter_readings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('fuel_shift_id')->constrained('fuel_shifts')->cascadeOnDelete();
            $table->foreignUlid('fuel_pump_id')->constrained('fuel_pumps')->restrictOnDelete();

            // Totalisers only ever go up. Sales are the difference, which is why
            // both ends are stored rather than a computed quantity.
            $table->decimal('opening_reading', 14, 3);
            $table->decimal('closing_reading', 14, 3)->nullable();

            // Litres pumped for testing and returned to the tank. Metered as a
            // sale but never sold, so it has to come back out of the figure.
            $table->decimal('test_litres', 12, 3)->default(0);

            $table->decimal('unit_price', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['fuel_shift_id', 'fuel_pump_id']);
        });

        Schema::create('fuel_tank_dips', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('fuel_shift_id')->constrained('fuel_shifts')->cascadeOnDelete();
            $table->foreignUlid('fuel_tank_id')->constrained('fuel_tanks')->restrictOnDelete();

            // What the stick or the gauge actually says is in there.
            $table->decimal('opening_dip', 12, 3);
            $table->decimal('closing_dip', 12, 3)->nullable();

            // Anything delivered mid-shift, which has to be added before the two
            // sides can be compared.
            $table->decimal('deliveries', 12, 3)->default(0);

            $table->timestamps();

            $table->unique(['fuel_shift_id', 'fuel_tank_id']);
        });

        Schema::create('fuel_prices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('menu_item_id')->constrained('menu_items')->cascadeOnDelete();

            // Three decimals: fuel is priced per litre to three places wherever
            // it is sold, and rounding to two moves real money.
            $table->decimal('price', 12, 3);
            $table->date('effective_from');

            $table->foreignUlid('team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            // Prices are announced centrally and change often; the history is
            // what lets an old shift be re-read at the price it actually sold at.
            $table->unique(['menu_item_id', 'effective_from']);
            $table->index('effective_from');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_prices');
        Schema::dropIfExists('fuel_tank_dips');
        Schema::dropIfExists('fuel_meter_readings');
        Schema::dropIfExists('fuel_shifts');
        Schema::dropIfExists('fuel_pumps');
        Schema::dropIfExists('fuel_tanks');
    }
};
