<?php

namespace App\Services\WhatsApp;

use App\Enums\PaymentMethod;
use App\Models\Tenant\Transfer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class WhatsAppSender
{
    public function __construct(private readonly WhatsAppSettings $settings) {}

    public function sendNewOrder(Transfer $transfer): WhatsAppResult
    {
        if ($transfer->payment_method !== PaymentMethod::Bank) {
            return WhatsAppResult::skipped('not_bank');
        }

        $account = $transfer->account;

        if (! $account) {
            Log::debug('WhatsApp skipped: order has no account.', ['transferId' => $transfer->id]);

            return WhatsAppResult::skipped('no_bank');
        }

        if ($account->trashed()) {
            Log::debug('WhatsApp skipped: account is deleted.', ['transferId' => $transfer->id, 'accountId' => $account->id]);

            return WhatsAppResult::skipped('bank_deleted');
        }

        $phone = $account->whatsapp_phone;

        if (! filled($phone)) {
            Log::debug('WhatsApp skipped: account has no number.', ['transferId' => $transfer->id, 'accountId' => $account->id]);

            return WhatsAppResult::skipped('no_number');
        }

        $cacheKey = $this->dedupeKey($transfer);

        if (! Cache::add($cacheKey, 1, (int) config('whatsapp.dedupe_ttl', 3600))) {
            Log::debug('WhatsApp skipped: already sent for this order.', ['transferId' => $transfer->id]);

            return WhatsAppResult::skipped('duplicate');
        }

        $link = OrderConfirmationLink::for($transfer);

        $result = $this->send(
            $phone,
            NewOrderMessage::forVariables($transfer, $link, $this->settings->variableCount()),
            NewOrderMessage::build($transfer, $link),
        );

        if (! $result->sent) {
            Cache::forget($cacheKey);
        }

        return $result;
    }

    public function send(string $rawPhone, array $parameters, ?string $readable = null): WhatsAppResult
    {
        if (! $this->settings->enabled()) {
            return WhatsAppResult::skipped('disabled');
        }

        $to = PhoneNumber::toE164($rawPhone, $this->settings->countryCode());

        if ($to === null) {
            Log::warning('WhatsApp number could not be normalised.', ['phone' => PhoneNumber::mask($rawPhone)]);

            return WhatsAppResult::skipped('unnormalisable');
        }

        return match ($this->settings->driver()) {
            'cloud' => $this->sendViaCloud($to, $parameters),
            'null' => WhatsAppResult::sent(),
            default => $this->sendViaLog($to, $readable ?? implode("\n", $parameters)),
        };
    }

    private function sendViaCloud(string $to, array $parameters): WhatsAppResult
    {
        $token = (string) $this->settings->get(WhatsAppSettings::TOKEN);
        $phoneNumberId = (string) $this->settings->get(WhatsAppSettings::PHONE_NUMBER_ID);

        if (blank($token) || blank($phoneNumberId)) {
            Log::warning('WhatsApp cloud driver is selected but not configured; falling back to the log driver.');

            return $this->sendViaLog($to, implode("\n", $parameters));
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
                        'name' => $this->settings->get(WhatsAppSettings::TEMPLATE_NEW_ORDER),
                        'language' => ['code' => $this->settings->get(WhatsAppSettings::TEMPLATE_LANGUAGE)],
                        'components' => [[
                            'type' => 'body',

                            'parameters' => array_map(
                                fn (string $value) => ['type' => 'text', 'text' => $value],
                                array_values($parameters),
                            ),
                        ]],
                    ],
                ]);
        } catch (Throwable $e) {
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

    private function sendViaLog(string $to, string $body): WhatsAppResult
    {
        Log::info('WhatsApp message (log driver - not actually sent).', [
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
