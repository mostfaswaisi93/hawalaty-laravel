<?php

namespace Tests\Feature\Admin;

use App\Enums\LicenseStatus;
use App\Models\Admin;
use App\Models\Box;
use App\Models\License;
use App\Models\Package;
use App\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Covers the Super Admin web portal: every page renders for an authenticated
 * admin, guests are redirected to login, and the platform-DB write paths
 * (CRUD + the payment→box→license renewal money flow) behave correctly.
 * License provisioning, backups and the tenant "peek" page are excluded here
 * because they need a real MySQL server, not the sqlite test database.
 */
class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create();
    }

    public static function pages(): array
    {
        return [
            'dashboard' => ['/admin/dashboard'],
            'licenses' => ['/admin/licenses'],
            'packages' => ['/admin/packages'],
            'boxes' => ['/admin/boxes'],
            'partners' => ['/admin/partners'],
            'payments' => ['/admin/payments'],
            'finance' => ['/admin/finance'],
            'backups' => ['/admin/backups'],
            'logs' => ['/admin/logs'],
            'settings' => ['/admin/settings'],
        ];
    }

    #[DataProvider('pages')]
    public function test_admin_page_renders(string $uri): void
    {
        $this->actingAs($this->admin(), 'admin')->get($uri)->assertOk();
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/admin/login');
    }

    public function test_can_create_a_package(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/packages', [
                'name' => 'Pro',
                'price' => '99.00',
                'deviceLimit' => '5',
                'features' => "Reports\nDaily backup",
            ])
            ->assertRedirect('/admin/packages');

        $package = Package::firstOrFail();
        $this->assertSame('Pro', $package->name);
        $this->assertSame(['Reports', 'Daily backup'], $package->features);
    }

    public function test_can_create_a_box_and_partner(): void
    {
        $this->actingAs($this->admin(), 'admin')->post('/admin/boxes', ['name' => 'Main', 'balance' => '250'])->assertRedirect();
        $this->actingAs($this->admin(), 'admin')->post('/admin/partners', ['name' => 'Ali', 'percentage' => '40'])->assertRedirect();

        $this->assertDatabaseHas('boxes', ['box_name' => 'Main']);
        $this->assertDatabaseHas('partners', ['name' => 'Ali']);
    }

    public function test_can_upsert_a_setting(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post('/admin/settings', ['key' => 'site_name', 'value' => 'Hawalaty'])->assertRedirect('/admin/settings');
        $this->assertDatabaseHas('settings', ['key' => 'site_name', 'value' => 'Hawalaty']);

        // Same key again updates in place instead of duplicating.
        $this->actingAs($admin, 'admin')->post('/admin/settings', ['key' => 'site_name', 'value' => 'Changed'])->assertRedirect();
        $this->assertDatabaseCount('settings', 1);
        $this->assertDatabaseHas('settings', ['key' => 'site_name', 'value' => 'Changed']);
    }

    public function test_recording_a_payment_credits_the_box_and_renews_the_license(): void
    {
        $package = Package::create(['name' => 'Basic', 'price' => 10, 'device_limit' => 1, 'features' => []]);
        $box = Box::create(['box_name' => 'Main', 'box_balance' => 100]);
        $license = License::create([
            'license_code' => 'TESTCODE',
            'customer_name' => 'Acme',
            'package_id' => $package->id,
            'status' => LicenseStatus::Trial,
            'expired_at' => now()->subDay(),
            'customer_balance' => 0,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/payments', ['licenseId' => $license->id, 'boxId' => $box->id, 'amount' => '150'])
            ->assertRedirect('/admin/payments');

        $this->assertDatabaseHas('payments', ['license_id' => $license->id, 'box_id' => $box->id, 'amount' => '150.00']);
        $this->assertSame('250.00', $box->fresh()->box_balance);

        $license->refresh();
        $this->assertSame(LicenseStatus::Active, $license->status);
        $this->assertTrue($license->expired_at->isFuture());
    }

    public function test_per_page_selector_controls_page_size(): void
    {
        foreach (range(1, 12) as $i) {
            Partner::create(['name' => "Partner {$i}", 'percentage' => 1]);
        }

        // Default (10) → 2 pages of 12 rows.
        $this->actingAs($this->admin(), 'admin')->get('/admin/partners')
            ->assertOk()
            ->assertViewHas('partners', fn ($p) => $p->perPage() === 10 && $p->total() === 12 && $p->lastPage() === 2);

        // per_page=50 → everything on one page.
        $this->actingAs($this->admin(), 'admin')->get('/admin/partners?per_page=50')
            ->assertOk()
            ->assertViewHas('partners', fn ($p) => $p->perPage() === 50 && $p->lastPage() === 1);

        // A value outside the whitelist falls back to 10.
        $this->actingAs($this->admin(), 'admin')->get('/admin/partners?per_page=9999')
            ->assertViewHas('partners', fn ($p) => $p->perPage() === 10);
    }

    public function test_finance_withdrawal_debits_the_box(): void
    {
        $box = Box::create(['box_name' => 'Main', 'box_balance' => 300]);
        $partner = Partner::create(['name' => 'Ali', 'percentage' => 50]);

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/finance/withdrawals', ['boxId' => $box->id, 'partnerId' => $partner->id, 'amount' => '120'])
            ->assertRedirect('/admin/finance');

        $this->assertSame('180.00', $box->fresh()->box_balance);
    }
}
