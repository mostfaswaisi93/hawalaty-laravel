<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dispensing controls.
 *
 * The most heavily regulated vertical of the twelve, and the one where the
 * system's job is to stop something rather than to record it. Two rules carry
 * the module:
 *
 *  - A prescription-only medicine cannot leave without a prescription. Not a
 *    warning, a refusal: dispensing Rx without one is an offence committed by
 *    whoever pressed the button, and a system that merely notes it afterwards
 *    has helped commit it.
 *
 *  - Controlled substances are dispensed into a register that is written once
 *    and never edited, because that register is what an inspector reads.
 *
 * Classification follows WHO ATC and the INN active-ingredient name, which is
 * also what makes "what else has the same ingredient" answerable at a counter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // Prescription-only versus over the counter.
            $table->boolean('is_prescription')->default(false)->after('service_minutes');

            // Where it sits on the national schedule of controlled drugs. Null
            // for everything that is not controlled, which is nearly everything.
            $table->string('control_schedule', 16)->nullable()->after('is_prescription');

            // WHO Anatomical Therapeutic Chemical code - the international
            // classification, and what groups therapeutic alternatives.
            $table->string('atc_code', 16)->nullable()->after('control_schedule');

            // International Nonproprietary Name: paracetamol, whatever the box
            // says. The daily question at a pharmacy counter is "what else has
            // this in it", and only the generic name answers it.
            $table->string('active_ingredient')->nullable()->after('atc_code');
            $table->string('strength', 64)->nullable()->after('active_ingredient');
            $table->string('dosage_form', 64)->nullable()->after('strength');

            // Kept between 2 and 8 degrees. Breaking the chain destroys the
            // stock, so it has to be visible before it is stored.
            $table->boolean('cold_chain')->default(false)->after('dosage_form');

            $table->index('atc_code');
            $table->index('active_ingredient');
        });

        Schema::create('dispense_records', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('menu_item_id')->constrained('menu_items')->restrictOnDelete();
            $table->foreignUlid('transfer_id')->nullable()->constrained('transfers')->nullOnDelete();
            $table->foreignUlid('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->string('patient_name');
            $table->string('patient_phone', 50)->nullable();

            // Identification is required for controlled substances in most
            // jurisdictions; optional otherwise.
            $table->string('patient_id_number', 64)->nullable();

            $table->string('prescriber_name')->nullable();
            $table->string('prescriber_licence', 64)->nullable();
            $table->string('prescription_number', 64)->nullable();
            $table->date('prescription_date')->nullable();

            $table->decimal('quantity', 12, 3);
            $table->string('control_schedule', 16)->nullable();
            $table->boolean('was_prescription')->default(false);

            $table->text('notes')->nullable();
            $table->foreignUlid('team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamp('dispensed_at');
            $table->timestamps();

            // The register is read by date and by substance.
            $table->index(['dispensed_at', 'control_schedule']);
            $table->index('menu_item_id');
            $table->index('prescription_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispense_records');

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex(['atc_code']);
            $table->dropIndex(['active_ingredient']);

            $table->dropColumn([
                'is_prescription', 'control_schedule', 'atc_code',
                'active_ingredient', 'strength', 'dosage_form', 'cold_chain',
            ]);
        });
    }
};
