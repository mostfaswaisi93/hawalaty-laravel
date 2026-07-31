<?php

namespace Tests\Feature\WhatsApp;

use Tests\TestCase;

/**
 * The public Meta webhook: verification handshake + inbound POST. No tenant
 * connection is touched, so these run without the tenant MySQL schema.
 */
class WhatsAppWebhookTest extends TestCase
{
    public function test_it_echoes_the_challenge_when_the_verify_token_matches(): void
    {
        config(['whatsapp.verify_token' => 'secret-token']);

        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=secret-token&hub.challenge=CHAL42')
            ->assertOk()
            ->assertSee('CHAL42');
    }

    public function test_it_rejects_a_wrong_verify_token(): void
    {
        config(['whatsapp.verify_token' => 'secret-token']);

        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=nope&hub.challenge=CHAL42')
            ->assertForbidden();
    }

    public function test_it_rejects_verification_when_no_token_is_configured(): void
    {
        // An empty configured token must never match an empty supplied token.
        config(['whatsapp.verify_token' => '']);

        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=&hub.challenge=CHAL42')
            ->assertForbidden();
    }

    public function test_it_accepts_an_inbound_post_when_no_app_secret_is_set(): void
    {
        config(['whatsapp.app_secret' => '']);

        $this->postJson('/webhooks/whatsapp', ['object' => 'whatsapp_business_account', 'entry' => []])
            ->assertOk();
    }

    public function test_it_refuses_a_post_with_a_bad_signature_when_an_app_secret_is_set(): void
    {
        config(['whatsapp.app_secret' => 'sekret']);

        $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'HTTP_X-Hub-Signature-256' => 'sha256=deadbeef',
            'CONTENT_TYPE' => 'application/json',
        ], '{"object":"x"}')
            ->assertForbidden();
    }

    public function test_it_accepts_a_post_with_a_valid_signature(): void
    {
        config(['whatsapp.app_secret' => 'sekret']);
        $body = '{"object":"whatsapp_business_account","entry":[]}';

        $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'HTTP_X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, 'sekret'),
            'CONTENT_TYPE' => 'application/json',
        ], $body)
            ->assertOk();
    }
}
