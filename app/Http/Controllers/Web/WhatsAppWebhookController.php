<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Meta WhatsApp Cloud API webhook - the single public callback Meta calls to
 * (1) verify the endpoint when the callback URL is registered, and (2) deliver
 * inbound messages and message-status updates thereafter.
 *
 * Public and login-free by necessity: Meta carries no session and no
 * X-License-Code. It is CSRF-exempt (registered in bootstrap/app.php) because
 * the POST is a server-to-server call, not a browser form.
 *
 * The endpoint is shared across tenants, so it authenticates with the
 * platform-level `whatsapp.verify_token` (echoing the challenge) and, on
 * inbound POSTs, the `whatsapp.app_secret` HMAC signature - both from config/
 * .env. PHP rewrites the dotted `hub.*` query keys Meta sends to `hub_*`.
 */
class WhatsAppWebhookController extends Controller
{
    /**
     * GET verification handshake. Meta sends hub.mode=subscribe plus the
     * verify token we entered in its dashboard; we echo hub.challenge back as
     * plain text ONLY when the token matches, otherwise 403.
     */
    public function verify(Request $request): Response
    {
        $expected = (string) config('whatsapp.verify_token');
        $token = (string) $request->query('hub_verify_token', '');

        if ($request->query('hub_mode') === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            return response((string) $request->query('hub_challenge', ''), 200)
                ->header('Content-Type', 'text/plain');
        }

        Log::warning('WhatsApp webhook verification rejected.', ['mode' => $request->query('hub_mode')]);

        return response('Forbidden', 403);
    }

    /**
     * Inbound events. Meta expects a fast 2xx or it retries and eventually
     * disables the webhook, so this always returns 200 once accepted - any real
     * processing is best-effort and must never make the response hang or throw.
     * When an app secret is configured the payload signature is verified first;
     * a bad signature is refused so a forged POST cannot inject events.
     */
    public function receive(Request $request): Response
    {
        $appSecret = (string) config('whatsapp.app_secret');

        if ($appSecret !== '') {
            $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);
            $provided = (string) $request->header('X-Hub-Signature-256', '');

            if ($provided === '' || ! hash_equals($expected, $provided)) {
                Log::warning('WhatsApp webhook signature mismatch - payload refused.');

                return response('Invalid signature', 403);
            }
        }

        // Best-effort: record what arrived. Resolving the tenant by
        // phone_number_id and updating message status is a later enhancement;
        // acknowledging with 200 keeps the subscription healthy today.
        Log::info('WhatsApp webhook event received.', [
            'object' => $request->input('object'),
            'entries' => count((array) $request->input('entry', [])),
        ]);

        return response('', 200);
    }
}
