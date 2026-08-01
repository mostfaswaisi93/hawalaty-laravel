<?php

namespace App\Services\WhatsApp;

use App\Models\License;
use App\Models\Tenant\Transfer;

class OrderConfirmationLink
{
    public static function for(Transfer $transfer): ?string
    {
        if (blank($transfer->confirmation_token)) {
            return null;
        }

        $license = app()->bound('currentLicense') ? app('currentLicense') : null;

        if (! $license instanceof License || blank($license->license_code)) {
            return null;
        }

        $path = route('order.confirm', [
            'license' => $license->license_code,
            'token' => $transfer->confirmation_token,
        ], false);

        return rtrim((string) config('app.url'), '/').$path;
    }
}
