<?php

namespace Tests\Feature\Api;

use App\Models\Tenant\Bank;
use App\Models\Tenant\ConstantList;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Transfer;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every tenant API read endpoint, requested for real.
 *
 * The API is one of the two delivery surfaces this app advertises, and most of
 * it had never been called by a test - only the handful added most recently.
 * That is the state where a renamed column, a changed service signature or a
 * resource referencing a dropped relation breaks a mobile client and the whole
 * suite stays green, because nothing on the web side touches the same code.
 *
 * These are deliberately smoke tests, not assertions about business rules:
 * those live with the services. What they hold is narrower and, for an API,
 * more urgent - the endpoint answers at all, answers JSON, and refuses a guest.
 */
class TenantApiSmokeTest extends TenantApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    /**
     * Data on every table the endpoints read, so each one returns a populated
     * response. An endpoint that only ever sees an empty table can hide a
     * broken mapping in the branch that formats a row.
     */
    private function seedSomethingOfEverything(): void
    {
        $bank = Bank::factory()->create(['name' => 'بنك فلسطين', 'code' => 'PAL']);
        $category = MenuCategory::create(['name' => 'مشاوي', 'position' => 0]);
        MenuItem::factory()->create(['name' => 'شاورما', 'menu_category_id' => $category->id, 'price' => '20.00']);
        Transfer::factory()->create(['customer' => 'أحمد', 'bank_id' => $bank->id]);

        $list = ConstantList::create(['key' => 'suppliers', 'title' => 'الموردون']);
        $list->items()->create(['value' => 'مورد أول', 'position' => 0]);
    }

    /** @return array<string, array{string}> */
    public static function readEndpoints(): array
    {
        return [
            'dashboard stats' => ['/api/v1/dashboard/stats'],
            'dashboard weekly' => ['/api/v1/dashboard/weekly-activity'],
            'transfers' => ['/api/v1/transfers'],
            'history' => ['/api/v1/history'],
            'banks' => ['/api/v1/banks'],
            'menu items' => ['/api/v1/menu-items'],
            'menu categories' => ['/api/v1/menu-categories'],
            'expenses' => ['/api/v1/expenses'],
            'constant lists' => ['/api/v1/constant-lists'],
            'team' => ['/api/v1/team'],
            'roles' => ['/api/v1/roles'],
            'devices' => ['/api/v1/devices'],
            'activity log' => ['/api/v1/activity-log'],
            // The API requires an explicit window where the web page defaults
            // one - deliberate: a client asking for "the report" without saying
            // which period is a bug in the client, not a request to guess.
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

    /** No endpoint may answer a request that carries no token. */
    #[DataProvider('readEndpoints')]
    public function test_the_endpoint_refuses_a_guest(string $uri): void
    {
        $this->getJson($uri)->assertUnauthorized();
    }

    /** ...and that refusal must come BEFORE validation, not after it. */
    public function test_the_report_endpoint_refuses_a_guest_before_validating(): void
    {
        // No date range either: an unauthenticated caller must be turned away
        // without being told which parameters the endpoint wants.
        $this->getJson('/api/v1/reports')->assertUnauthorized();
    }

    /**
     * A single order over the API, end to end. `transfers` is the one write
     * path a mobile client uses constantly, and the amount is recomputed
     * server-side - a client cannot name its own price.
     */
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
                // Deliberately understated: the server must ignore it.
                'price' => 1,
            ]],
        ])->assertCreated();

        $this->assertSame('80.00', Transfer::sole()->amount);
    }

    /**
     * The report endpoint returned 500 for any range holding a single order.
     *
     * ReportService narrows its select to the five columns a period summary
     * shows, and the controller wrapped those partial models in the full
     * TransferResource, which reads `payment_method->value` - a column the
     * report never loads. Every test passed, because none had ever called it.
     */
    public function test_the_report_endpoint_survives_a_range_containing_orders(): void
    {
        $this->actAsOwner();
        Transfer::factory()->count(3)->create(['created_at' => '2026-03-15 10:00:00']);

        $this->getJson('/api/v1/reports?from=2026-03-01&to=2026-03-31')
            ->assertOk()
            ->assertJsonCount(3, 'transfers')
            ->assertJsonPath('count', 3)
            // The summary shape, not a half-populated full transfer.
            ->assertJsonStructure(['transfers' => [['id', 'customer', 'date', 'status', 'amount']]]);
    }

    /** The show route resolves a real record rather than 404ing on a valid id. */
    public function test_a_single_order_can_be_fetched(): void
    {
        $this->actAsOwner();
        $transfer = Transfer::factory()->create(['customer' => 'سامي']);

        $this->getJson("/api/v1/transfers/{$transfer->id}")
            ->assertOk()
            ->assertJsonPath('data.customer', 'سامي');
    }
}
