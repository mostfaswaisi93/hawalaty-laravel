<?php

namespace Tests\Feature\WhatsApp;

use App\Enums\PaymentMethod;
use App\Enums\TransferStatus;
use App\Models\Tenant\Bank;
use App\Models\Tenant\Transfer;
use App\Models\Tenant\TransferLineItem;
use App\Services\WhatsApp\WhatsAppSender;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * No RefreshDatabase and no saved models: the tenant schema
 * (database/migrations/tenant) cannot run against the sqlite test database
 * because several of those migrations use MySQL-only DDL. Every model here is
 * unsaved with its relations set explicitly, so nothing can lazy-load.
 *
 * The invariant every case shares: sendNewOrder() never throws, whatever
 * happens.
 */
class WhatsAppSenderTest extends TestCase
{
    private function sender(): WhatsAppSender
    {
        return new WhatsAppSender;
    }

    private function transfer(array $attributes = [], Bank|false|null $bank = null): Transfer
    {
        $bank ??= new Bank(['name' => 'بنك فلسطين', 'code' => 'BOP', 'phone' => '+972595817016']);

        $transfer = new Transfer(array_merge([
            'customer' => 'أحمد محمد',
            'status' => TransferStatus::Pending,
            'amount' => '72.00',
            'item_name' => 'شاورما دجاج ×2',
            'payment_method' => PaymentMethod::Bank,
        ], $attributes));

        // Unique per call so the dedupe cache key never collides across cases.
        $transfer->id = (string) Str::ulid();
        $transfer->created_at = now();

        // setRelation even for null: otherwise ->bank would try to lazy-load.
        $transfer->setRelation('bank', $bank === false ? null : $bank);
        $transfer->setRelation('lineItems', new Collection([
            new TransferLineItem(['name' => 'شاورما دجاج', 'quantity' => 2, 'price' => '30.00']),
        ]));

        return $transfer;
    }

    // ---------------------------------------------------------------- guards

    public function test_it_skips_a_cash_order(): void
    {
        Http::fake();

        $result = $this->sender()->sendNewOrder($this->transfer(['payment_method' => PaymentMethod::Cash]));

        $this->assertTrue($result->skipped);
        $this->assertSame('not_bank', $result->errorCode);
        Http::assertNothingSent();
    }

    public function test_it_skips_when_the_order_has_no_bank(): void
    {
        Http::fake();

        $result = $this->sender()->sendNewOrder($this->transfer(bank: false));

        $this->assertSame('no_bank', $result->errorCode);
        Http::assertNothingSent();
    }

    public function test_it_skips_a_soft_deleted_bank(): void
    {
        Http::fake();

        $bank = new Bank(['name' => 'بنك قديم', 'code' => 'OLD', 'phone' => '+972595817016']);
        $bank->deleted_at = now();

        $result = $this->sender()->sendNewOrder($this->transfer(bank: $bank));

        $this->assertSame('bank_deleted', $result->errorCode);
        Http::assertNothingSent();
    }

    public function test_it_skips_a_bank_with_no_number(): void
    {
        Http::fake();

        $result = $this->sender()->sendNewOrder(
            $this->transfer(bank: new Bank(['name' => 'بنك', 'code' => 'X', 'phone' => null]))
        );

        $this->assertSame('no_number', $result->errorCode);
        Http::assertNothingSent();
    }

    public function test_it_skips_an_unnormalisable_number(): void
    {
        Http::fake();

        $result = $this->sender()->sendNewOrder(
            $this->transfer(bank: new Bank(['name' => 'بنك', 'code' => 'X', 'phone' => 'not-a-number']))
        );

        $this->assertSame('unnormalisable', $result->errorCode);
        Http::assertNothingSent();
    }

    public function test_it_skips_when_the_feature_is_disabled(): void
    {
        config(['whatsapp.enabled' => false]);
        Http::fake();

        $result = $this->sender()->sendNewOrder($this->transfer());

        $this->assertSame('disabled', $result->errorCode);
        Http::assertNothingSent();
    }

    public function test_it_sends_only_once_for_the_same_order(): void
    {
        config(['whatsapp.driver' => 'log']);

        $transfer = $this->transfer();
        $sender = $this->sender();

        $this->assertTrue($sender->sendNewOrder($transfer)->sent);

        $second = $sender->sendNewOrder($transfer);

        $this->assertFalse($second->sent);
        $this->assertSame('duplicate', $second->errorCode);
    }

    // --------------------------------------------------------------- drivers

    public function test_log_driver_reports_success_without_any_http_call(): void
    {
        config(['whatsapp.driver' => 'log']);
        Http::fake();

        $result = $this->sender()->sendNewOrder($this->transfer());

        $this->assertTrue($result->sent);
        Http::assertNothingSent();
    }

    public function test_an_unknown_driver_falls_back_to_log_instead_of_throwing(): void
    {
        config(['whatsapp.driver' => 'nonsense-typo']);
        Http::fake();

        $result = $this->sender()->sendNewOrder($this->transfer());

        $this->assertTrue($result->sent);
        Http::assertNothingSent();
    }

    public function test_cloud_driver_posts_an_approved_template(): void
    {
        config([
            'whatsapp.driver' => 'cloud',
            'whatsapp.token' => 'test-token',
            'whatsapp.phone_number_id' => '123456',
            'whatsapp.api_version' => 'v21.0',
            'whatsapp.base_url' => 'https://graph.facebook.com',
        ]);

        Http::fake([
            '*' => Http::response(['messages' => [['id' => 'wamid.TEST']]], 200),
        ]);

        $result = $this->sender()->sendNewOrder($this->transfer());

        $this->assertTrue($result->sent);
        $this->assertSame('wamid.TEST', $result->providerMessageId);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://graph.facebook.com/v21.0/123456/messages'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                // Digits only - Meta rejects a leading '+' in `to`.
                && $body['to'] === '972595817016'
                // Free-form text would be rejected with error 131047, because a
                // bank's number never opens a 24h customer-initiated window.
                && $body['type'] === 'template'
                && count($body['template']['components'][0]['parameters']) === 1;
        });
    }

    public function test_cloud_driver_without_credentials_falls_back_to_log(): void
    {
        config(['whatsapp.driver' => 'cloud', 'whatsapp.token' => null, 'whatsapp.phone_number_id' => null]);
        Http::fake();

        $result = $this->sender()->sendNewOrder($this->transfer());

        $this->assertTrue($result->sent);
        Http::assertNothingSent();
    }

    // -------------------------------------------------------------- failures

    public function test_a_rejected_send_fails_without_throwing(): void
    {
        config(['whatsapp.driver' => 'cloud', 'whatsapp.token' => 't', 'whatsapp.phone_number_id' => '1']);

        Http::fake([
            '*' => Http::response(['error' => ['code' => 190, 'message' => 'Access token expired']], 401),
        ]);

        $result = $this->sender()->sendNewOrder($this->transfer());

        $this->assertFalse($result->sent);
        $this->assertSame(401, $result->httpStatus);
        $this->assertSame('190', $result->errorCode);
    }

    public function test_a_connection_timeout_fails_without_throwing(): void
    {
        config(['whatsapp.driver' => 'cloud', 'whatsapp.token' => 't', 'whatsapp.phone_number_id' => '1']);

        // A timeout raises ConnectionException - there is no HTTP status on this
        // path, which is exactly why it is the one most often missed.
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));

        $result = $this->sender()->sendNewOrder($this->transfer());

        $this->assertFalse($result->sent);
        $this->assertSame('connection_error', $result->errorCode);
    }

    public function test_a_failed_send_releases_the_dedupe_key_so_it_can_be_retried(): void
    {
        config(['whatsapp.driver' => 'cloud', 'whatsapp.token' => 't', 'whatsapp.phone_number_id' => '1']);
        Http::fake(['*' => Http::response(['error' => ['code' => 500]], 500)]);

        $transfer = $this->transfer();
        $sender = $this->sender();

        $this->assertFalse($sender->sendNewOrder($transfer)->sent);

        // Second attempt must reach the provider again, not be suppressed as a
        // duplicate - a failure must not poison the key.
        $second = $sender->sendNewOrder($transfer);

        $this->assertNotSame('duplicate', $second->errorCode);
    }
}
