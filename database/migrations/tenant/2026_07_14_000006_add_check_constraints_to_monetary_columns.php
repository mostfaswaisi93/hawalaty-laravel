<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONSTRAINTS = [
        ['expenses', 'expenses_amount_non_negative', 'amount >= 0'],
        ['transfers', 'transfers_amount_non_negative', 'amount >= 0'],
        ['transfers', 'transfers_change_amount_non_negative', 'change_amount >= 0'],
        ['transfers', 'transfers_transferred_amount_non_negative', 'transferred_amount >= 0'],
        ['transfer_line_items', 'transfer_line_items_price_non_negative', 'price >= 0'],
        ['menu_items', 'menu_items_price_non_negative', 'price >= 0'],
    ];

    public function up(): void
    {
        foreach (self::CONSTRAINTS as [$table, $name, $expression]) {
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})");
        }
    }

    public function down(): void
    {
        foreach (self::CONSTRAINTS as [$table, $name, $expression]) {
            DB::statement("ALTER TABLE `{$table}` DROP CONSTRAINT `{$name}`");
        }
    }
};
