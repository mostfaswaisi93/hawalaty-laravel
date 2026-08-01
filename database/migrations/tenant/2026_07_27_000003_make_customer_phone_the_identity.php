<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::getConnection();

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
