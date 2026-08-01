<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
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

        Log::info('WhatsApp webhook event received.', [
            'object' => $request->input('object'),
            'entries' => count((array) $request->input('entry', [])),
        ]);

        return response('', 200);
    }
}
