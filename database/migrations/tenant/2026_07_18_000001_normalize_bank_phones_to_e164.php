<?php

use App\Services\WhatsApp\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data-only migration — no schema change.
 *
 * `banks.phone` is the WhatsApp number new-order notifications go to, and is now
 * stored in E.164 and validated against it on write. Existing rows hold local
 * formats like "0599000001", which would fail that new rule the next time an
 * operator edits the bank — producing an inexplicable error on a field they did
 * not touch. This backfills them through the SAME PhoneNumber::toE164() the form
 * uses, so the two can never diverge.
 *
 * Runs only via `php artisan tenants:migrate --all` (or per-license). Plain
 * `php artisan migrate` does NOT run database/migrations/tenant.
 *
 * Uses the query builder rather than the Bank model so soft-deleted rows are
 * included too — a retired bank still appears on historical orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('tenant');

        $connection->table('banks')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($banks) use ($connection) {
                foreach ($banks as $bank) {
                    $normalised = PhoneNumber::toE164($bank->phone);

                    // Leave anything unparseable exactly as it is: a human needs
                    // to look at it, and destroying it here would hide that.
                    if ($normalised === null || $normalised === $bank->phone) {
                        continue;
                    }

                    $connection->table('banks')
                        ->where('id', $bank->id)
                        ->update(['phone' => $normalised]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible by design: the original local formatting is not
        // recoverable from the E.164 value, and reverting would only reintroduce
        // numbers the application can no longer dial.
    }
};
