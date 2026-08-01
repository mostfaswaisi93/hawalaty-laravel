<?php

namespace Tests\Feature\Api;

use App\Enums\LicenseStatus;
use App\Models\Admin;
use App\Models\Box;
use App\Models\Expense;
use App\Models\License;
use App\Models\Package;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Tenant\TeamMember;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminApiSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    private function seedThePlatform(): void
    {
        $package = Package::create(['name' => 'Starter', 'price' => 30, 'device_limit' => 2, 'features' => ['a']]);
        $box = Box::create(['box_name' => 'الصندوق الرئيسي', 'box_balance' => 1000]);
        $partner = Partner::create(['name' => 'شريك', 'percentage' => 40]);

        $license = License::create([
            'license_code' => 'SMOKE001',
            'customer_name' => 'مطعم التجربة',
            'package_id' => $package->id,
            'status' => LicenseStatus::Active,
            'expired_at' => now()->addMonth(),
            'customer_balance' => 0,
        ]);

        Payment::create(['license_id' => $license->id, 'box_id' => $box->id, 'amount' => 200]);
        Withdrawal::create(['box_id' => $box->id, 'partner_id' => $partner->id, 'amount' => 50]);
        Expense::create(['box_id' => $box->id, 'description' => 'استضافة', 'amount' => 30, 'date' => now()->toDateString()]);
    }

    public static function readEndpoints(): array
    {
        return [
            'packages' => ['/api/v1/admin/packages'],
            'licenses' => ['/api/v1/admin/licenses'],
            'boxes' => ['/api/v1/admin/boxes'],
            'partners' => ['/api/v1/admin/partners'],
            'withdrawals' => ['/api/v1/admin/withdrawals'],
            'payments' => ['/api/v1/admin/payments'],
            'expenses' => ['/api/v1/admin/expenses'],
            'settings' => ['/api/v1/admin/settings'],
            'activity log' => ['/api/v1/admin/activity-log'],
            'finance summary' => ['/api/v1/admin/finance/summary'],
            'settlements' => ['/api/v1/admin/settlements'],
            'settlement preview' => ['/api/v1/admin/settlements/preview'],
            'backups' => ['/api/v1/admin/backups'],
        ];
    }

    #[DataProvider('readEndpoints')]
    public function test_the_endpoint_answers_json_for_an_admin(string $uri): void
    {
        $this->actingAsAdmin();
        $this->seedThePlatform();

        $response = $this->getJson($uri)->assertOk();

        $this->assertIsArray($response->json(), "{$uri} did not answer with a JSON structure.");
    }

    #[DataProvider('readEndpoints')]
    public function test_the_endpoint_refuses_a_guest(string $uri): void
    {
        $this->getJson($uri)->assertUnauthorized();
    }

    #[DataProvider('readEndpoints')]
    public function test_the_endpoint_refuses_a_tenant_token(string $uri): void
    {
        Sanctum::actingAs(new TeamMember(['name' => 'موظف مستأجر']), ['*']);

        $response = $this->getJson($uri);

        $this->assertContains(
            $response->getStatusCode(),
            [401, 403],
            "{$uri} answered a tenant token with {$response->getStatusCode()}.",
        );
    }

    public function test_a_single_licence_can_be_fetched_without_a_tenant_database(): void
    {
        $this->actingAsAdmin();
        $this->seedThePlatform();

        $this->getJson('/api/v1/admin/licenses/'.License::sole()->id)
            ->assertOk()
            ->assertJsonPath('license.licenseCode', 'SMOKE001')
            ->assertJsonPath('license.customerName', 'مطعم التجربة')
            ->assertJsonCount(0, 'team')
            ->assertJsonCount(0, 'recentActivity');
    }

    public function test_the_settlement_preview_reconciles_on_an_empty_platform(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/settlements/preview')
            ->assertOk()
            ->assertJsonPath('reconciles', true)
            ->assertJsonPath('distributableProfit', 0);
    }
}
