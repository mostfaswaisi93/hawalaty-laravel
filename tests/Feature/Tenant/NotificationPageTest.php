<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\TeamMember;
use App\Notifications\Tenant\NewOrderNotification;
use App\Notifications\Tenant\OrderStatusChangedNotification;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

/**
 * The full notifications page (/notifications). The bell dropdown still hits the
 * same route for JSON; the page is what a normal browser visit gets.
 */
class NotificationPageTest extends TenantTestCase
{
    private function owner(): TeamMember
    {
        return TeamMember::factory()->create(['role_id' => $this->seedStandardRoles()['owner']->id]);
    }

    private function notify(TeamMember $member, array $data, string $type = NewOrderNotification::class, bool $read = false): void
    {
        $member->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'data' => $data,
            'read_at' => $read ? now() : null,
        ]);
    }

    public function test_the_page_lists_a_notification_with_its_details(): void
    {
        $owner = $this->owner();
        $this->notify($owner, ['kind' => 'new_order', 'transferId' => 'T1', 'customer' => 'محمود العلي', 'amount' => 72.0, 'itemName' => 'شاورما دجاج']);

        $this->actingAs($owner, 'tenant')->get('/notifications')
            ->assertOk()
            ->assertViewHas('notifications')
            ->assertSee('محمود العلي')
            ->assertSee('72.00')
            ->assertSee('شاورما دجاج');
    }

    public function test_it_shows_the_status_label_for_a_status_change(): void
    {
        $owner = $this->owner();
        $this->notify($owner, ['kind' => 'order_status_changed', 'transferId' => 'T2', 'customer' => 'بحسن', 'amount' => 80.0, 'status' => 'completed'], OrderStatusChangedNotification::class);

        $this->actingAs($owner, 'tenant')->get('/notifications')
            ->assertOk()
            ->assertSee('بحسن')
            ->assertSee(__('مستلمة'));
    }

    public function test_the_bell_route_still_returns_json(): void
    {
        $owner = $this->owner();
        $this->notify($owner, ['kind' => 'new_order', 'transferId' => 'T3', 'customer' => 'x', 'amount' => 1.0]);

        $this->actingAs($owner, 'tenant')->getJson('/notifications')
            ->assertOk()
            ->assertJsonStructure(['notifications', 'unreadCount']);
    }

    public function test_mark_all_read_from_the_page_redirects_and_clears_unread(): void
    {
        $owner = $this->owner();
        $this->notify($owner, ['kind' => 'new_order', 'transferId' => 'T4', 'customer' => 'x', 'amount' => 1.0]);

        $this->assertSame(1, $owner->unreadNotifications()->count());

        $this->actingAs($owner, 'tenant')->post('/notifications/read-all')->assertRedirect();

        $this->assertSame(0, $owner->fresh()->unreadNotifications()->count());
    }

    public function test_an_empty_page_renders_without_error(): void
    {
        $this->actingAs($this->owner(), 'tenant')->get('/notifications')
            ->assertOk()
            ->assertSee(__('لا توجد إشعارات بعد'));
    }
}
