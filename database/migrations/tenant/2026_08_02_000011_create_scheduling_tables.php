<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time as the scarce thing.
 *
 * The one vertical where nothing is stocked. A workshop or a salon does not run
 * out of goods, it runs out of hours and people, so the module that matters is
 * the diary rather than the shelf.
 *
 * Work orders are the other half, and the reason they cannot be a separate
 * silo: a garage invoice mixes parts off the shelf with labour by the hour. Both
 * are catalogue items here - labour is simply an item of kind `service` priced
 * per hour - which is what lets a finished job be invoiced through the ordinary
 * till, depleting stock and posting to the ledger by the same path as any other
 * sale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // How long one of these takes. Drives the default end time when the
            // service is booked; zero means it is not something you book.
            $table->unsignedSmallInteger('service_minutes')->default(0)->after('warranty_months');
        });

        Schema::create('service_resources', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');

            // A resource is whatever the work needs and there is only one of:
            // a technician, a lift, a chair.
            $table->enum('kind', ['technician', 'bay', 'equipment'])->default('technician');

            // Linked when the resource is a person on the team, so their name
            // and their diary stay the same thing.
            $table->foreignUlid('team_member_id')->nullable()->constrained('team_members')->nullOnDelete();

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique('name');
            $table->index('active');
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->foreignUlid('service_resource_id')->nullable()->constrained('service_resources')->nullOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->string('customer_name');
            $table->string('customer_phone', 50)->nullable();

            $table->timestamp('starts_at');
            $table->timestamp('ends_at');

            $table->enum('status', ['booked', 'confirmed', 'in_progress', 'done', 'cancelled', 'no_show'])
                ->default('booked');

            // Money taken up front. Under IFRS 15 this is not revenue yet - the
            // service has not been delivered - so it is held here rather than
            // being posted as a sale.
            $table->decimal('deposit', 12, 2)->default(0);

            $table->text('notes')->nullable();
            $table->foreignUlid('team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            // The overlap check reads this on every booking.
            $table->index(['service_resource_id', 'starts_at', 'ends_at']);
            $table->index(['starts_at', 'status']);
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference', 64)->nullable();

            $table->foreignUlid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone', 50)->nullable();

            $table->foreignUlid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignUlid('service_resource_id')->nullable()->constrained('service_resources')->nullOnDelete();

            // What came in. Free text on purpose: a garage records a plate and a
            // reading, a repair shop records a model, and forcing either into
            // the other's shape helps nobody.
            $table->string('subject')->nullable();
            $table->string('registration', 32)->nullable();
            $table->unsignedInteger('odometer')->nullable();

            $table->text('complaint')->nullable();
            $table->text('diagnosis')->nullable();

            $table->enum('status', ['received', 'diagnosing', 'quoted', 'approved', 'in_progress', 'done', 'delivered', 'cancelled'])
                ->default('received');

            $table->decimal('parts_total', 14, 2)->default(0);
            $table->decimal('labour_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            // Set once the job has been billed, which is the point stock and the
            // ledger move - through the ordinary sale path, not a second one.
            $table->foreignUlid('transfer_id')->nullable()->constrained('transfers')->nullOnDelete();

            $table->timestamp('received_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // How long the shop stands behind its own work, as opposed to the
            // manufacturer's warranty on the parts.
            $table->unsignedSmallInteger('workmanship_warranty_days')->default(0);

            $table->foreignUlid('team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'received_at']);
            $table->index('reference');
            $table->index('registration');
        });

        Schema::create('work_order_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('work_order_id')->constrained('work_orders')->cascadeOnDelete();

            // Always a catalogue item, parts and labour alike. That is what lets
            // the finished job be invoiced through the ordinary till instead of
            // needing an invoicing path of its own.
            $table->foreignUlid('menu_item_id')->constrained('menu_items')->restrictOnDelete();

            $table->string('name');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 12, 3)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index('work_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_lines');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('service_resources');

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('service_minutes');
        });
    }
};
