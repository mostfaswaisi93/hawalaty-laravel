<?php

namespace Tests\Feature\Api;

use App\Enums\PaymentMethod;
use App\Http\Middleware\ResolveTenantConnection;
use App\Models\Tenant\Account;
use App\Models\Tenant\Customer;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Role;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\TransferService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class TenantApiCoverageTest extends TenantApiTestCase
{
    public function test_customers_are_listed_with_their_aggregates(): void
    {
        Notification::fake();
        $this->actAsOwner();

        $item = MenuItem::factory()->create(['price' => '25.25']);
        app(TransferService::class)->create([
            'customer' => 'أحمد',
            'customerPhone' => '0599111222',
            'paymentMethod' => PaymentMethod::Cash,
            'items' => [['itemId' => $item->id, 'quantity' => 2]],
        ]);

        $this->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonPath('data.0.phone', '0599111222')
            ->assertJsonPath('data.0.orderCount', 1)
            ->assertJsonPath('data.0.totalSpent', 50.5);
    }

    public function test_customers_can_be_searched_by_phone(): void
    {
        $this->actAsOwner();
        Customer::factory()->create(['name' => 'سارة', 'phone' => '0598765432']);
        Customer::factory()->create(['name' => 'وليد', 'phone' => '0591111111']);

        $this->getJson('/api/v1/customers?search=8765432')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'سارة');
    }

    public function test_a_customer_detail_returns_a_page_of_their_orders(): void
    {
        $this->actAsOwner();
        $customer = Customer::factory()->create(['phone' => '0599000111']);
        Transfer::factory()->count(3)->create(['customer_id' => $customer->id]);

        $this->getJson("/api/v1/customers/{$customer->id}?per_page=2")
            ->assertOk()
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonCount(2, 'transfers.data')
            ->assertJsonPath('transfers.meta.total', 3);
    }

    public function test_notifications_are_listed_with_an_unread_count(): void
    {
        $member = $this->actAsOwner();
        $this->pushNotification($member);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.unreadCount', 1);
    }

    public function test_a_notification_can_be_marked_read(): void
    {
        $member = $this->actAsOwner();
        $this->pushNotification($member);
        $id = $member->notifications()->first()->id;

        $this->postJson("/api/v1/notifications/{$id}/read")
            ->assertOk()
            ->assertJsonPath('unreadCount', 0);
    }

    public function test_a_member_cannot_mark_another_members_notification_read(): void
    {
        $other = TeamMember::factory()->create(['role_id' => $this->roles['staff']->id]);
        $this->pushNotification($other);
        $id = $other->notifications()->first()->id;

        $this->actAsOwner();

        $this->postJson("/api/v1/notifications/{$id}/read")->assertNotFound();
        $this->assertSame(1, $other->fresh()->unreadNotifications()->count());
    }

    public function test_search_finds_orders_and_customers(): void
    {
        $this->actAsOwner();
        Customer::factory()->create(['name' => 'محمود سالم', 'phone' => '0599123321']);
        Transfer::factory()->create(['customer' => 'محمود سالم']);

        $this->getJson('/api/v1/search?q=محمود')
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonCount(1, 'customers');
    }

    public function test_a_one_character_query_returns_nothing(): void
    {
        $this->actAsOwner();
        Customer::factory()->create(['name' => 'محمود', 'phone' => '0599123321']);

        $this->getJson('/api/v1/search?q=م')
            ->assertOk()
            ->assertJsonCount(0, 'customers');
    }

    public function test_search_hides_banks_and_catalogue_from_a_member_without_those_permissions(): void
    {
        Account::factory()->create(['holder_name' => 'بنك فلسطين']);
        $category = MenuCategory::create(['name' => 'مشاوي', 'position' => 0]);
        MenuItem::factory()->create(['name' => 'بنك برجر', 'menu_category_id' => $category->id]);

        $powerless = Role::factory()->create();
        $member = TeamMember::factory()->create(['role_id' => $powerless->id]);
        Sanctum::actingAs($member, ['*']);

        $this->getJson('/api/v1/search?q=بنك')
            ->assertOk()
            ->assertJsonCount(0, 'banks')
            ->assertJsonCount(0, 'menuItems');
    }

    public function test_search_shows_banks_and_catalogue_to_a_member_who_may_manage_them(): void
    {
        $this->actAsOwner();
        Account::factory()->create(['holder_name' => 'بنك فلسطين']);
        $category = MenuCategory::create(['name' => 'مشاوي', 'position' => 0]);
        MenuItem::factory()->create(['name' => 'بنك برجر', 'menu_category_id' => $category->id]);

        $this->getJson('/api/v1/search?q=بنك')
            ->assertOk()
            ->assertJsonCount(1, 'banks')
            ->assertJsonCount(1, 'menuItems');
    }

    public function test_the_receipt_is_returned_as_a_pdf(): void
    {
        $this->actAsOwner();
        $transfer = Transfer::factory()->create();

        $response = $this->get("/api/v1/transfers/{$transfer->id}/receipt");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString("receipt-{$transfer->id}.pdf", (string) $response->headers->get('content-disposition'));
    }

    public function test_a_guest_cannot_reach_any_of_the_new_endpoints(): void
    {
        foreach (['/api/v1/customers', '/api/v1/notifications', '/api/v1/search?q=test'] as $uri) {
            $this->getJson($uri)->assertUnauthorized();
        }
    }

    public function test_the_license_header_is_required(): void
    {
        $this->withMiddleware(ResolveTenantConnection::class);
        $this->actAsOwner();

        $this->getJson('/api/v1/customers')
            ->assertStatus(422)
            ->assertJsonPath('message', 'The X-License-Code header is required.');
    }

    public function test_a_sync_update_without_a_target_is_rejected(): void
    {
        $this->actAsOwner();

        $this->postJson('/api/v1/sync/push', [
            'events' => [[
                'clientUuid' => 'uuid-1',
                'entityType' => 'transfer',
                'operation' => 'update',
                'payload' => ['status' => 'completed'],
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors('events.0.payload');
    }

    public function test_a_sync_delete_naming_its_target_is_accepted(): void
    {
        $this->actAsOwner();
        $transfer = Transfer::factory()->create();

        $this->postJson('/api/v1/sync/push', [
            'events' => [[
                'clientUuid' => 'uuid-del',
                'entityType' => 'transfer',
                'operation' => 'delete',
                'payload' => ['id' => $transfer->id],
            ]],
        ])->assertOk()->assertJsonPath('applied.0', 'uuid-del');

        $this->assertSoftDeleted('transfers', ['id' => $transfer->id], 'tenant');
    }

    private function pushNotification(TeamMember $member): void
    {
        $member->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TestNotification',
            'data' => ['message' => 'مرحباً'],
            'read_at' => null,
        ]);
    }
}
