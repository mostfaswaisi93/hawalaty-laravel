<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * expenses.amount, transfers.amount/change_amount/transferred_amount,
 * transfer_line_items.price, and menu_items.price are plain signed
 * decimal(10,2) columns - every current write path validates min:0 via a
 * FormRequest, but nothing at the DB layer stops a negative value from any
 * path that bypasses that specific request (a future sync/import/console
 * command, direct Eloquent/tinker use). CHECK constraints are the same
 * last-resort safety net the banks/team_members FK-restriction migrations
 * already apply to a different class of risk. MySQL only enforces CHECK
 * from 8.0.16+ (confirmed 8.4.3 here) - raw SQL is used since this Laravel
 * version's Schema\Blueprint has no native check() method.
 *
 * NULL columns (change_amount, transferred_amount) are unaffected: SQL
 * CHECK treats a NULL comparison as non-violating, so nullable rows with no
 * value still pass.
 */
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
