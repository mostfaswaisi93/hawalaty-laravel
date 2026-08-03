<?php

use App\Services\WhatsApp\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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

                    if ($normalised === null || $normalised === $bank->phone) {
                        continue;
                    }

                    $connection->table('banks')
                        ->where('id', $bank->id)
                        ->update(['phone' => $normalised]);
                }
            });
    }

    public function down(): void {}
};
