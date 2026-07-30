<?php

namespace App\Services\WhatsApp;

use App\Models\License;
use App\Models\Tenant\Transfer;

/**
 * Builds the public confirmation link embedded in the new-order WhatsApp
 * message.
 *
 * The link has to resolve a tenant with NO session and NO X-License-Code
 * header, so it carries the license code in the path - TenantConnectionResolver
 * reads it straight back - alongside the transfer's unguessable token. The
 * token is the real credential; the license code merely selects the database.
 *
 * Returns null (message goes out without a link, never breaks) when the order
 * has no token - a cash order - or when the current license is not resolvable,
 * e.g. an order created from a context that never bound `currentLicense`.
 */
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

        // Built on the CONFIGURED base URL (config('app.url')), never the request
        // host: this link is opened later on the bank owner's phone, so it must
        // be the app's stable public address - not "127.0.0.1", and not whatever
        // host an API/offline-sync order happened to arrive on. route(..., false)
        // gives the path; APP_URL supplies the scheme/host/port.
        $path = route('order.confirm', [
            'license' => $license->license_code,
            'token' => $transfer->confirmation_token,
        ], false);

        return rtrim((string) config('app.url'), '/').$path;
    }
}
