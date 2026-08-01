<?php

namespace Tests\Feature\Api;

use App\Models\Tenant\Account;
use App\Models\Tenant\ConstantList;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Transfer;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;

class TenantApiSmokeTest extends TenantApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function seedSomethingOfEverything(): void
    {
        $bank = Account::factory()->create(['holder_name' => 'بنك فلسطين']);
        $category = MenuCategory::create(['name' => 'مشاوي', 'position' => 0]);
        MenuItem::factory()->create(['name' => 'شاورما', 'menu_category_id' => $category->id, 'price' => '20.00']);
        Transfer::factory()->create(['customer' => 'أحمد', 'account_id' => $bank->id]);

        $list = ConstantList::create(['key' => 'suppliers', 'title' => 'الموردون']);
        $list->items()->create(['value' => 'مورد أول', 'position' => 0]);
    }

    public static function readEndpoints(): array
    {
        return [
            'dashboard stats' => ['/api/v1/dashboard/stats'],
            'dashboard weekly' => ['/api/v1/dashboard/weekly-activity'],
            'transfers' => ['/api/v1/transfers'],
            'history' => ['/api/v1/history'],
            'menu items' => ['/api/v1/menu-items'],
            'menu categories' => ['/api/v1/menu-categories'],
            'expenses' => ['/api/v1/expenses'],
            'constant lists' => ['/api/v1/constant-lists'],
            'team' => ['/api/v1/team'],
            'roles' => ['/api/v1/roles'],
            'devices' => ['/api/v1/devices'],
            'activity log' => ['/api/v1/activity-log'],

            'reports' => ['/api/v1/reports?from=2026-01-01&to=2026-12-31'],
            'sync pull' => ['/api/v1/sync/pull'],
        ];
    }

    #[DataProvider('readEndpoints')]
    public function test_the_endpoint_answers_json_for_a_permitted_member(string $uri): void
    {
        $this->actAsOwner();
        $this->seedSomethingOfEverything();

        $response = $this->getJson($uri)->assertOk();

        $this->assertIsArray(
            $response->json(),
            "{$uri} did not answer with a JSON structure.",
        );
    }

    #[DataProvider('readEndpoints')]
    public function test_the_endpoint_refuses_a_guest(string $uri): void
    {
        $this->getJson($uri)->assertUnauthorized();
    }

    public function test_the_report_endpoint_refuses_a_guest_before_validating(): void
    {
        $this->getJson('/api/v1/reports')->assertUnauthorized();
    }

    public function test_an_order_can_be_created_over_the_api(): void
    {
        $this->actAsOwner();
        $item = MenuItem::factory()->create(['name' => 'كباب', 'price' => '40.00']);

        $this->postJson('/api/v1/transfers', [
            'customer' => 'ليلى',
            'customerPhone' => '0599112233',
            'paymentMethod' => 'cash',
            'hasChange' => false,
            'items' => [[
                'itemId' => $item->id,
                'name' => $item->name,
                'quantity' => 2,

                'price' => 1,
            ]],
        ])->assertCreated();

        $this->assertSame('80.00', Transfer::sole()->amount);
    }

    public function test_the_report_endpoint_survives_a_range_containing_orders(): void
    {
        $this->actAsOwner();
        Transfer::factory()->count(3)->create(['created_at' => '2026-03-15 10:00:00']);

        $this->getJson('/api/v1/reports?from=2026-03-01&to=2026-03-31')
            ->assertOk()
            ->assertJsonCount(3, 'transfers')
            ->assertJsonPath('count', 3)

            ->assertJsonStructure(['transfers' => [['id', 'customer', 'date', 'status', 'amount']]]);
    }

    public function test_a_single_order_can_be_fetched(): void
    {
        $this->actAsOwner();
        $transfer = Transfer::factory()->create(['customer' => 'سامي']);

        $this->getJson("/api/v1/transfers/{$transfer->id}")
            ->assertOk()
            ->assertJsonPath('data.customer', 'سامي');
    }
}
