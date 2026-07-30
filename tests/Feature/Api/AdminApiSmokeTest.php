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

/**
 * Every platform API read endpoint, requested for real.
 *
 * The tenant half of this sweep turned up a /reports endpoint that returned
 * 500 for any range holding a single order - a resource reading columns its
 * query never selected. Nothing caught it because nothing had ever called it,
 * and the same was true of all forty routes under /api/v1/admin.
 *
 * Deliberately smoke tests: the endpoint answers, answers JSON, refuses a
 * guest, and refuses a TENANT token. That last one matters most here - Admin
 * and TeamMember both issue Sanctum tokens against the same table, so a valid
 * bearer token is not by itself proof of platform staff.
 */
class AdminApiSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    /**
     * One row in every platform table, so each endpoint formats a real record
     * rather than returning an empty list that hides a broken mapping.
     */
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

    /** @return array<string, array{string}> */
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

    /**
     * A tenant's own token must not open the platform console. Both guards
     * issue tokens against the same table, so this is the line that separates
     * a shop's staff from the operator running the platform.
     */
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

    /**
     * The licence detail route also peeks at the tenant's own database. A
     * licence with no `databaseConnection` skips that block entirely (see
     * LicenseService::show), so the endpoint is reachable here without MySQL
     * and must answer with the roster and activity keys present but empty -
     * not omitted, which would make a client branch on their absence.
     */
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

    /**
     * The clearing preview is the one read endpoint that computes rather than
     * lists, and its figures feed a document that cannot be edited once
     * confirmed - so it has to reconcile even with nothing to distribute.
     */
    public function test_the_settlement_preview_reconciles_on_an_empty_platform(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/settlements/preview')
            ->assertOk()
            ->assertJsonPath('reconciles', true)
            ->assertJsonPath('distributableProfit', 0);
    }
}
