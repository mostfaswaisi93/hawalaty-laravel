<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer IS a phone number, not a name.
 *
 * `customers.name` was UNIQUE and findOrCreate() matched on it, which is wrong
 * in both directions: two different people called "أحمد" collided into one
 * record and pooled their order history, while the SAME person whose name was
 * typed "أحمد محمد" once and "احمد محمد" the next time split into two. A phone
 * number is the thing that actually identifies a customer.
 *
 * The unique index moves to `phone`. It stays NULLable: MySQL permits many NULLs
 * under a unique index, which is what keeps historical rows captured before a
 * phone was collected - and orders taken without one - working untouched.
 *
 * Existing duplicate phones must be merged first or the index cannot be built.
 * The oldest row wins (it owns the longest history); the others' transfers are
 * repointed onto it and the empty shells deleted. A name is backfilled from a
 * loser only if the survivor has none, so nothing readable is thrown away.
 */
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::getConnection();

        // 1. Merge customers that share a phone.
        $duplicates = $db->table('customers')
            ->select('phone')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('phone');

        foreach ($duplicates as $phone) {
            $rows = $db->table('customers')
                ->where('phone', $phone)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'name']);

            $survivor = $rows->first();
            $losers = $rows->slice(1);

            foreach ($losers as $loser) {
                $db->table('transfers')->where('customer_id', $loser->id)->update(['customer_id' => $survivor->id]);

                if (blank($survivor->name) && filled($loser->name)) {
                    $db->table('customers')->where('id', $survivor->id)->update(['name' => $loser->name]);
                    $survivor->name = $loser->name;
                }
            }

            $db->table('customers')->whereIn('id', $losers->pluck('id'))->delete();
        }

        // 2. Move the unique constraint from name to phone. `name` keeps a plain
        //    index because the customers page still searches on it.
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->index('name');
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropIndex(['name']);
            $table->unique('name');
        });
    }
};
