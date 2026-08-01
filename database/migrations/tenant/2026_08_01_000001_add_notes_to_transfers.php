<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A free-text note on an order.
 *
 * This is what the /constants custom lists write into. Those lists - delivery
 * methods, common order notes, cancellation reasons - were fully built (model,
 * service, API, policy) but nothing in the web app ever READ them: a tenant
 * could define "طرق التوصيل" and then never pick one anywhere. The order screen
 * now offers each list as a dropdown, and the chosen values land here.
 *
 * Deliberately ONE text column rather than a row per list. A tenant may add or
 * delete lists at will, so a column per list would need a migration each time,
 * and a join table would make an order's note unreadable without reassembling
 * it. The note is descriptive text that gets printed on the receipt - storing it
 * as the text it will be shown as keeps the receipt honest even after the list
 * that suggested a value has been deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('transfers', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('item_name');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('transfers', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
