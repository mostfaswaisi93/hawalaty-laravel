<?php

namespace Tests\Feature\Api;

use App\Models\Tenant\Account;
use App\Models\Tenant\Expense;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Role;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

class TenantApiWriteTest extends TenantApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function actAsPowerless(): TeamMember
    {
        $member = TeamMember::factory()->create(['role_id' => Role::factory()->create()->id]);
        Sanctum::actingAs($member, ['*']);

        return $member;
    }

    public function test_a_catalogue_entry_can_be_created_and_priced(): void
    {
        $this->actAsOwner();
        $category = MenuCategory::create(['name' => 'مشاوي', 'position' => 0]);

        $this->postJson('/api/v1/menu-items', [
            'name' => 'كباب',
            'menuCategoryId' => $category->id,
            'price' => '45.50',
            'active' => true,
        ])->assertCreated();

        $this->assertSame('45.500', MenuItem::sole()->price);
    }

    public function test_a_member_without_the_permission_cannot_touch_the_catalogue(): void
    {
        $this->actAsPowerless();
        $category = MenuCategory::create(['name' => 'مشاوي', 'position' => 0]);

        $this->postJson('/api/v1/menu-items', [
            'name' => 'كباب', 'menuCategoryId' => $category->id, 'price' => '10', 'active' => true,
        ])->assertForbidden();

        $this->assertSame(0, MenuItem::count());
    }

    public function test_an_expense_can_be_recorded_and_removed(): void
    {
        $this->actAsOwner();
        $bank = Account::factory()->create();

        $this->postJson('/api/v1/expenses', [
            'description' => 'كهرباء',
            'amount' => '120.00',
            'accountId' => $bank->id,
            'date' => '2026-03-01',
        ])->assertCreated();

        $expense = Expense::sole();
        $this->assertSame('120.00', $expense->amount);

        $this->deleteJson("/api/v1/expenses/{$expense->id}")->assertNoContent();
        $this->assertSame(0, Expense::count());
    }

    public function test_an_order_status_can_be_changed_but_not_its_amount(): void
    {
        $this->actAsOwner();
        $transfer = Transfer::factory()->create(['status' => 'pending', 'amount' => '75.00']);

        $this->putJson("/api/v1/transfers/{$transfer->id}", [
            'status' => 'completed',
            'amount' => '5.00',
        ])->assertOk();

        $transfer->refresh();
        $this->assertSame('completed', $transfer->status->value);
        $this->assertSame('75.00', $transfer->amount, 'A client rewrote the order amount.');
    }

    public function test_an_order_can_be_deleted_softly(): void
    {
        $this->actAsOwner();
        $transfer = Transfer::factory()->create();

        $this->deleteJson("/api/v1/transfers/{$transfer->id}")->assertNoContent();

        $this->assertSame(0, Transfer::count());
        $this->assertSame(1, Transfer::withTrashed()->count());
    }

    public function test_a_team_member_can_be_invited(): void
    {
        $this->actAsOwner();

        $this->postJson('/api/v1/team', [
            'name' => 'موظف جديد',
            'email' => 'new@member.test',
            'password' => 'Str0ngPassw0rd',
            'roleId' => $this->roles['staff']->id,
            'active' => true,
        ])->assertCreated();

        $this->assertNotNull(TeamMember::where('email', 'new@member.test')->first());
    }

    public function test_a_member_without_the_permission_cannot_invite(): void
    {
        $this->actAsPowerless();

        $this->postJson('/api/v1/team', [
            'name' => 'x', 'email' => 'x@x.test', 'password' => 'Str0ngPassw0rd',
            'roleId' => $this->roles['staff']->id, 'active' => true,
        ])->assertForbidden();
    }
}
