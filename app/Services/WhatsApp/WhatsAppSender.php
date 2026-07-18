<?php

namespace App\Services\WhatsApp;

use App\Enums\PaymentMethod;
use App\Models\Tenant\Transfer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends the "new order" WhatsApp message to the number stored on the bank or
 * wallet the operator selected for the order (`banks.phone`).
 *
 * Two design constraints shape this class:
 *
 * 1. It runs SYNCHRONOUSLY, inside the order-creation request. It is not a
 *    queued job on purpose: QUEUE_CONNECTION is `database` and no worker is
 *    assumed to be running (see App\Notifications\Tenant\NewOrderNotification),
 *    so dispatching would write a `jobs` row that is never processed and report
 *    success. Worse, App\Services\TenantConnectionResolver mutates process-global
 *    config, so a long-lived worker would leak one tenant's database connection
 *    into another tenant's job. Timeouts are therefore deliberately short.
 *
 * 2. Nothing here may ever throw. The order is already committed by the time
 *    this runs; a WhatsApp problem is not an order problem. Every failure path
 *    returns a WhatsAppResult and logs.
 *
 * send() takes primitives only, so the day a dedicated worker exists this can be
 * moved behind a queued job without touching its inputs.
 */
class WhatsAppSender
{
    /**
     * Applies every "should this order notify at all?" rule, then sends.
     */
    public function sendNewOrder(Transfer $transfer): WhatsAppResult
    {
        // Enum identity, never the string 'bank'.
        if ($transfer->payment_method !== PaymentMethod::Bank) {
            return WhatsAppResult::skipped('not_bank');
        }

        $bank = $transfer->bank;

        if (! $bank) {
            Log::debug('WhatsApp skipped: order has no bank.', ['transferId' => $transfer->id]);

            return WhatsAppResult::skipped('no_bank');
        }

        // Transfer::bank() is withTrashed(), so a retired bank still resolves.
        if ($bank->trashed()) {
            Log::debug('WhatsApp skipped: bank is deleted.', ['transferId' => $transfer->id, 'bankId' => $bank->id]);

            return WhatsAppResult::skipped('bank_deleted');
        }

        // `active` is deliberately NOT checked: the operator chose this bank for
        // this order, so flipping the flag afterwards must not suppress it.
        if (! filled($bank->phone)) {
            Log::debug('WhatsApp skipped: bank has no number.', ['transferId' => $transfer->id, 'bankId' => $bank->id]);

            return WhatsAppResult::skipped('no_number');
        }

        // Guards a double-submitted order form or a retried API call. Keyed on
        // the transfer ULID, so a genuinely different order still sends.
        $cacheKey = $this->dedupeKey($transfer);

        if (! Cache::add($cacheKey, 1, (int) config('whatsapp.dedupe_ttl', 3600))) {
            Log::debug('WhatsApp skipped: already sent for this order.', ['transferId' => $transfer->id]);

            return WhatsAppResult::skipped('duplicate');
        }

        $result = $this->send($bank->phone, NewOrderMessage::build($transfer));

        // Do not let a failure poison the key against a later manual resend.
        if (! $result->sent) {
            Cache::forget($cacheKey);
        }

        return $result;
    }

    /**
     * Primitives only — no Eloquent, no tenant connection.
     */
    public function send(string $rawPhone, string $body): WhatsAppResult
    {
        if (! config('whatsapp.enabled', true)) {
            return WhatsAppResult::skipped('disabled');
        }

        $to = PhoneNumber::toE164($rawPhone);

        if ($to === null) {
            Log::warning('WhatsApp number could not be normalised.', ['phone' => PhoneNumber::mask($rawPhone)]);

            return WhatsAppResult::skipped('unnormalisable');
        }

        // An unrecognised driver falls through to `log` rather than throwing: a
        // typo in .env must not break order creation.
        return match (config('whatsapp.driver', 'log')) {
            'cloud' => $this->sendViaCloud($to, $body),
            'null' => WhatsAppResult::sent(),
            default => $this->sendViaLog($to, $body),
        };
    }

    /**
     * Meta WhatsApp Cloud API. Always sends an approved TEMPLATE: free-form text
     * to a number that never messaged us first is rejected with error 131047.
     */
    private function sendViaCloud(string $to, string $body): WhatsAppResult
    {
        $token = (string) config('whatsapp.token');
        $phoneNumberId = (string) config('whatsapp.phone_number_id');

        if (blank($token) || blank($phoneNumberId)) {
            Log::warning('WhatsApp cloud driver is selected but not configured; falling back to the log driver.');

            return $this->sendViaLog($to, $body);
        }

        $url = rtrim((string) config('whatsapp.base_url'), '/')
            .'/'.config('whatsapp.api_version')
            .'/'.$phoneNumberId.'/messages';

        $startedAt = microtime(true);

        try {
            $response = Http::withToken($token)
                ->connectTimeout((int) config('whatsapp.connect_timeout', 3))
                ->timeout((int) config('whatsapp.timeout', 5))
                ->acceptJson()
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => PhoneNumber::toWireFormat($to),
                    'type' => 'template',
                    'template' => [
                        'name' => config('whatsapp.template.new_order'),
                        'language' => ['code' => config('whatsapp.template.language')],
                        'components' => [[
                            'type' => 'body',
                            // Exactly one positional parameter — a count mismatch
                            // against the approved template returns error 132000.
                            'parameters' => [['type' => 'text', 'text' => $body]],
                        ]],
                    ],
                ]);
        } catch (Throwable $e) {
            // A connect/read timeout raises ConnectionException — there is no
            // HTTP status to inspect on this path.
            Log::error('WhatsApp send failed to reach the provider.', [
                'phone' => PhoneNumber::mask($to),
                'durationMs' => $this->elapsedMs($startedAt),
                'exception' => $e,
            ]);

            return WhatsAppResult::failed(null, 'connection_error', $e->getMessage(), $this->elapsedMs($startedAt));
        }

        $durationMs = $this->elapsedMs($startedAt);

        if (! $response->successful()) {
            Log::error('WhatsApp send rejected by the provider.', [
                'phone' => PhoneNumber::mask($to),
                'httpStatus' => $response->status(),
                'providerErrorCode' => $response->json('error.code'),
                'providerError' => $response->json('error.message'),
                'durationMs' => $durationMs,
            ]);

            return WhatsAppResult::failed(
                $response->status(),
                (string) $response->json('error.code'),
                (string) $response->json('error.message'),
                $durationMs,
            );
        }

        $messageId = $response->json('messages.0.id');

        Log::info('WhatsApp message sent.', [
            'phone' => PhoneNumber::mask($to),
            'providerMessageId' => $messageId,
            'durationMs' => $durationMs,
        ]);

        return WhatsAppResult::sent($messageId, $response->status(), $durationMs);
    }

    /**
     * The default driver, and the demo path. This is the one place the number is
     * logged unmasked — seeing the exact destination and the exact body is the
     * entire purpose of the driver. Watch it with `php artisan pail`.
     */
    private function sendViaLog(string $to, string $body): WhatsAppResult
    {
        Log::info('WhatsApp message (log driver — not actually sent).', [
            'to' => $to,
            'body' => $body,
        ]);

        return WhatsAppResult::sent('log-'.Str::ulid());
    }

    private function dedupeKey(Transfer $transfer): string
    {
        $license = app()->bound('currentLicense') ? app('currentLicense')->license_code : 'default';

        return "wa:new-order:{$license}:{$transfer->id}";
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
