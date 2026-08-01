<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\Account;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\TenantSetting;
use App\Services\Tenant\TransferService;
use App\Services\WhatsApp\WhatsAppSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TenantTestCase;

class OrderWhatsAppRoutingTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            WhatsAppSettings::ENABLED => '1',
            WhatsAppSettings::DRIVER => 'cloud',
            WhatsAppSettings::TOKEN => 'test-token',
            WhatsAppSettings::PHONE_NUMBER_ID => '1234567890',
            WhatsAppSettings::TEMPLATE_NEW_ORDER => 'new_order',
            WhatsAppSettings::TEMPLATE_LANGUAGE => 'ar',
        ] as $key => $value) {
            TenantSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.TEST']]], 200)]);
    }

    private function menuItem(float $price = 50): MenuItem
    {
        return MenuItem::factory()->create([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'price' => $price,
            'active' => true,
        ]);
    }

    private function payload(array $overrides): array
    {
        $item = $this->menuItem();

        return [
            'customer' => 'زبون',
            'items' => [[
                'itemId' => $item->id,
                'name' => $item->name,
                'quantity' => 1,
                'price' => 50.0,
            ]],
            ...$overrides,
        ];
    }

    private function assertSentTo(string $expected): void
    {
        Http::assertSent(function (Request $request) use ($expected) {
            return ($request->data()['to'] ?? null) === $expected;
        });
    }

    public function test_it_notifies_the_chosen_accounts_whatsapp_number(): void
    {
        $account = Account::factory()->create(['whatsapp_phone' => '+972595817016']);

        app(TransferService::class)->create($this->payload([
            'paymentMethod' => 'bank',
            'accountId' => $account->id,
            'transferredAmount' => 50,
            'referenceNumber' => 'TRX-'.\Illuminate\Support\Str::random(10),
        ]));

        $this->assertSentTo('972595817016');
    }

    public function test_it_notifies_the_chosen_account_and_not_another(): void
    {
        Account::factory()->create(['whatsapp_phone' => '+972599999999']);
        $chosen = Account::factory()->create(['whatsapp_phone' => '+972595817016']);

        app(TransferService::class)->create($this->payload([
            'paymentMethod' => 'bank',
            'accountId' => $chosen->id,
            'transferredAmount' => 50,
            'referenceNumber' => 'TRX-'.\Illuminate\Support\Str::random(10),
        ]));

        $this->assertSentTo('972595817016');
    }

    public function test_a_wallet_account_is_notified_too(): void
    {
        $wallet = Account::factory()->wallet()->create(['whatsapp_phone' => '+972599000123']);

        app(TransferService::class)->create($this->payload([
            'paymentMethod' => 'bank',
            'accountId' => $wallet->id,
            'transferredAmount' => 50,
            'referenceNumber' => 'TRX-'.\Illuminate\Support\Str::random(10),
        ]));

        $this->assertSentTo('972599000123');
    }

    public function test_an_account_without_a_number_sends_nothing(): void
    {
        $account = Account::factory()->create(['whatsapp_phone' => null]);

        app(TransferService::class)->create($this->payload([
            'paymentMethod' => 'bank',
            'accountId' => $account->id,
            'transferredAmount' => 50,
            'referenceNumber' => 'TRX-'.\Illuminate\Support\Str::random(10),
        ]));

        Http::assertNothingSent();
    }

    public function test_a_cash_order_sends_nothing(): void
    {
        app(TransferService::class)->create($this->payload([
            'paymentMethod' => 'cash',
            'hasChange' => false,
        ]));

        Http::assertNothingSent();
    }

    public function test_the_message_names_the_chosen_account(): void
    {
        $account = Account::factory()->create([
            'holder_name' => 'أحمد محمد',
            'whatsapp_phone' => '+972595817016',
        ]);

        app(TransferService::class)->create($this->payload([
            'paymentMethod' => 'bank',
            'accountId' => $account->id,
            'transferredAmount' => 50,
            'referenceNumber' => 'TRX-'.\Illuminate\Support\Str::random(10),
        ]));

        Http::assertSent(function (Request $request) {
            $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            return str_contains((string) $body, 'أحمد محمد');
        });
    }
}
