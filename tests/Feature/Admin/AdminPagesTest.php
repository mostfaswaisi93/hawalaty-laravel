<?php

namespace Tests\Feature\Admin;

use App\Enums\LicenseStatus;
use App\Exceptions\InsufficientBoxBalanceException;
use App\Models\Admin;
use App\Models\Box;
use App\Models\Expense;
use App\Models\License;
use App\Models\Package;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Withdrawal;
use App\Services\Admin\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

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
            'settlements' => ['/admin/settlements'],
            'backups' => ['/admin/backups'],
            'contact messages' => ['/admin/contact-messages'],
            'logs' => ['/admin/logs'],
            'settings' => ['/admin/settings'],
            'profile' => ['/admin/profile'],
        ];
    }

    public function test_the_license_detail_page_renders_without_a_tenant_database(): void
    {
        $package = Package::create(['name' => 'Starter', 'price' => 30, 'device_limit' => 2, 'features' => []]);
        $license = License::create([
            'license_code' => 'DETAIL01',
            'customer_name' => 'مطعم التجربة',
            'package_id' => $package->id,
            'status' => LicenseStatus::Active,
            'expired_at' => now()->addMonth(),
            'customer_balance' => 0,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get("/admin/licenses/{$license->id}")
            ->assertOk()
            ->assertSee('مطعم التجربة')
            ->assertSee('DETAIL01')

            ->assertSee(__('لا يوجد أعضاء فريق'))
            ->assertSee(__('لا يوجد نشاط'));
    }

    public function test_the_roster_notice_is_absent_when_nothing_was_cut(): void
    {
        $package = Package::create(['name' => 'Basic', 'price' => 20, 'device_limit' => 1, 'features' => []]);
        $license = License::create([
            'license_code' => 'DETAIL02',
            'customer_name' => 'Acme',
            'package_id' => $package->id,
            'status' => LicenseStatus::Active,
            'expired_at' => now()->addMonth(),
            'customer_balance' => 0,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get("/admin/licenses/{$license->id}")
            ->assertOk()
            ->assertDontSee('من أصل');
    }

    public function test_a_filter_form_carries_the_chosen_page_size(): void
    {
        $response = $this->actingAs($this->admin(), 'admin')
            ->get('/admin/licenses?per_page=50')
            ->assertOk();

        $response->assertSee('name="per_page" value="50"', false);
    }

    public function test_no_page_size_input_is_emitted_by_default(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/licenses')
            ->assertOk()
            ->assertDontSee('name="per_page" value=', false);
    }

    public function test_admin_can_update_their_own_profile(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->put('/admin/profile', ['name' => 'Renamed Admin', 'email' => 'renamed@hawalaty.test'])
            ->assertRedirect('/admin/profile');

        $admin->refresh();
        $this->assertSame('Renamed Admin', $admin->name);
        $this->assertSame('renamed@hawalaty.test', $admin->email);
    }

    public function test_a_blank_password_leaves_the_current_one_untouched(): void
    {
        $admin = $this->admin();
        $original = $admin->password;

        $this->actingAs($admin, 'admin')
            ->put('/admin/profile', ['name' => $admin->name, 'email' => $admin->email, 'password' => ''])
            ->assertRedirect('/admin/profile');

        $this->assertSame($original, $admin->refresh()->password);
    }

    public function test_profile_rejects_an_email_already_taken_by_another_admin(): void
    {
        $other = Admin::factory()->create(['email' => 'taken@hawalaty.test']);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->put('/admin/profile', ['name' => $admin->name, 'email' => $other->email])
            ->assertSessionHasErrors('email');

        $this->assertNotSame($other->email, $admin->refresh()->email);
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

    public function test_dashboard_renders_with_real_data(): void
    {
        $package = Package::create(['name' => 'Basic', 'price' => 25, 'device_limit' => 2, 'features' => []]);
        $box = Box::create(['box_name' => 'Main', 'box_balance' => 500]);
        Partner::create(['name' => 'Ali', 'percentage' => 40]);

        $license = License::create([
            'license_code' => 'TRENDCOD',
            'customer_name' => 'Acme',
            'package_id' => $package->id,
            'status' => LicenseStatus::Active,

            'expired_at' => now()->addDays(10),
            'customer_balance' => 0,
        ]);

        Payment::create(['license_id' => $license->id, 'box_id' => $box->id, 'amount' => 120]);
        Expense::create(['box_id' => $box->id, 'description' => 'Hosting', 'amount' => 40, 'date' => now()->toDateString()]);

        $this->actingAs($this->admin(), 'admin')->get('/admin/dashboard')
            ->assertOk()
            ->assertViewHas('netProfit', 80.0)
            ->assertViewHas('totalBoxBalance', 500.0)
            ->assertViewHas('trend', fn ($trend) => count($trend) === 6)
            ->assertViewHas('expiringSoon', fn ($rows) => $rows->count() === 1)
            ->assertViewHas('packageDistribution', fn ($rows) => $rows[0]['count'] === 1 && $rows[0]['share'] === 100.0)
            ->assertViewHas('partners', fn ($rows) => $rows[0]['computedShare'] === 200.0);
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

    public function test_can_upsert_a_custom_setting(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post('/admin/settings', ['key' => 'custom_flag', 'value' => 'Hawalaty'])->assertRedirect('/admin/settings');
        $this->assertDatabaseHas('settings', ['key' => 'custom_flag', 'value' => 'Hawalaty']);

        $this->actingAs($admin, 'admin')->post('/admin/settings', ['key' => 'custom_flag', 'value' => 'Changed'])->assertRedirect();
        $this->assertDatabaseCount('settings', 1);
        $this->assertDatabaseHas('settings', ['key' => 'custom_flag', 'value' => 'Changed']);
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

        $this->actingAs($this->admin(), 'admin')->get('/admin/partners')
            ->assertOk()
            ->assertViewHas('partners', fn ($p) => $p->perPage() === 10 && $p->total() === 12 && $p->lastPage() === 2);

        $this->actingAs($this->admin(), 'admin')->get('/admin/partners?per_page=50')
            ->assertOk()
            ->assertViewHas('partners', fn ($p) => $p->perPage() === 50 && $p->lastPage() === 1);

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

    public function test_a_withdrawal_cannot_overdraw_its_box(): void
    {
        $box = Box::create(['box_name' => 'Main', 'box_balance' => 300]);
        $partner = Partner::create(['name' => 'Ali', 'percentage' => 50]);

        $this->actingAs($this->admin(), 'admin')
            ->from('/admin/finance')
            ->post('/admin/finance/withdrawals', ['boxId' => $box->id, 'partnerId' => $partner->id, 'amount' => '500'])
            ->assertRedirect('/admin/finance')
            ->assertSessionHasErrors('amount');

        $this->assertSame('300.00', $box->fresh()->box_balance, 'The box was debited despite the refusal.');
        $this->assertSame(0, Withdrawal::count());
    }

    public function test_a_platform_expense_cannot_overdraw_its_box(): void
    {
        $box = Box::create(['box_name' => 'Main', 'box_balance' => 50]);

        $this->actingAs($this->admin(), 'admin')
            ->from('/admin/finance')
            ->post('/admin/finance/expenses', [
                'boxId' => $box->id,
                'description' => 'استضافة',
                'amount' => '80',
                'date' => now()->toDateString(),
            ])
            ->assertRedirect('/admin/finance')
            ->assertSessionHasErrors('amount');

        $this->assertSame('50.00', $box->fresh()->box_balance);
        $this->assertSame(0, Expense::count());
    }

    public function test_the_service_itself_refuses_an_overdraw_even_when_validation_is_bypassed(): void
    {
        $box = Box::create(['box_name' => 'Main', 'box_balance' => 100]);
        $partner = Partner::create(['name' => 'Ali', 'percentage' => 50]);

        $this->expectException(InsufficientBoxBalanceException::class);

        try {
            app(WithdrawalService::class)->create([
                'boxId' => $box->id,
                'partnerId' => $partner->id,
                'amount' => '250',
            ]);
        } finally {
            $this->assertSame('100.00', $box->fresh()->box_balance);
            $this->assertSame(0, Withdrawal::count());
        }
    }

    public function test_a_withdrawal_of_the_entire_balance_is_allowed(): void
    {
        $box = Box::create(['box_name' => 'Main', 'box_balance' => 300]);
        $partner = Partner::create(['name' => 'Ali', 'percentage' => 50]);

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/finance/withdrawals', ['boxId' => $box->id, 'partnerId' => $partner->id, 'amount' => '300'])
            ->assertRedirect('/admin/finance')
            ->assertSessionHasNoErrors();

        $this->assertSame('0.00', $box->fresh()->box_balance);
    }
}
