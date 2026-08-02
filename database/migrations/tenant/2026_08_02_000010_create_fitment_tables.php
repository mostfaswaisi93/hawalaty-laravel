<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which part fits which vehicle.
 *
 * The question at a parts counter is never "is it in stock" - it is "does it fit
 * this car". Everything else in the catalogue answers the first; only this
 * answers the second, and getting it wrong sends someone home with a box that
 * does not go on.
 *
 * Modelled on how the trade's own data standards work: TecDoc in Europe and the
 * Middle East, ACES in North America. Both express fitment as a part linked to a
 * vehicle over a year range with optional engine qualification - not a row per
 * individual year, which would multiply the same fact by twelve.
 *
 * Cross-references are the other half. A customer arrives holding an old part
 * with a number stamped on it, and that number is as likely to be the
 * manufacturer's as anyone's. Every number that has ever named this part has to
 * find it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_makes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');

            // World Manufacturer Identifier: the first three characters of a
            // VIN under ISO 3779. Lets a pasted chassis number name its own make.
            $table->string('wmi', 3)->nullable();

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique('name');
            $table->index('wmi');
        });

        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('vehicle_make_id')->constrained('vehicle_makes')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['vehicle_make_id', 'name']);
        });

        Schema::create('item_fitments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignUlid('vehicle_model_id')->constrained('vehicle_models')->cascadeOnDelete();

            // A range, because that is how the fact exists. Null on either side
            // means open-ended - "from 2015 onwards" is a real answer.
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();

            // Free text: the same model year splits by engine in ways no fixed
            // list survives across makes.
            $table->string('engine', 64)->nullable();

            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['vehicle_model_id', 'year_from', 'year_to']);
            $table->index('menu_item_id');
        });

        Schema::create('item_references', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('menu_item_id')->constrained('menu_items')->cascadeOnDelete();

            // oe = the vehicle manufacturer's own number, which is the one
            // stamped on the part the customer is holding.
            $table->enum('type', ['oe', 'aftermarket'])->default('oe');

            $table->string('reference', 64);
            $table->string('brand')->nullable();
            $table->timestamps();

            $table->unique(['menu_item_id', 'reference']);

            // The lookup that matters: any number, straight to the part.
            $table->index('reference');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            // A part number that was withdrawn and replaced. Restricted rather
            // than cascaded: deleting the replacement would strand the chain.
            $table->foreignUlid('superseded_by_item_id')->nullable()->after('parent_item_id')
                ->constrained('menu_items')->nullOnDelete();

            // A refundable deposit taken until the old unit comes back -
            // standard on alternators, batteries and steering racks.
            $table->decimal('core_charge', 12, 2)->default(0)->after('cost');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('superseded_by_item_id');
            $table->dropColumn('core_charge');
        });

        Schema::dropIfExists('item_references');
        Schema::dropIfExists('item_fitments');
        Schema::dropIfExists('vehicle_models');
        Schema::dropIfExists('vehicle_makes');
    }
};
