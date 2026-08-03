<?php

namespace Tests\Feature\Admin;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\Package;
use App\Services\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class TenantProvisioningNamingTest extends TestCase
{
    use RefreshDatabase;

    private function invokeHidden(string $method, array $args = []): mixed
    {
        $m = new ReflectionMethod(TenantProvisioner::class, $method);
        $m->setAccessible(true);

        return $m->invokeArgs(app(TenantProvisioner::class), $args);
    }

    public function test_generated_license_code_is_always_ascii_alphanumeric(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $code = $this->invokeHidden('generateLicenseCode');
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $code);
        }
    }

    public function test_an_ascii_license_code_clears_the_identifier_whitelist(): void
    {
        try {
            $this->invokeHidden('provisionPhysicalDatabase', ['AR12CD34']);
            $this->fail('Expected a database connection failure without a live MySQL server.');
        } catch (Throwable $e) {
            $this->assertStringNotContainsString(
                'safety whitelist',
                $e->getMessage(),
                'The whitelist wrongly rejected a valid ASCII license code.'
            );

            $this->assertStringContainsString('`license_ar12cd34`', $e->getMessage());
        }
    }

    public function test_a_non_ascii_license_code_is_rejected_before_any_ddl_runs(): void
    {
        $this->expectException(RuntimeException::class);

        $this->invokeHidden('provisionPhysicalDatabase', ['مطعم']);
    }

    public function test_an_arabic_business_name_round_trips_intact(): void
    {
        $package = Package::create(['name' => 'Basic', 'price' => 10, 'device_limit' => 1, 'features' => []]);
        $name = 'مطعم البركة للمأكولات الشعبية';

        $license = License::create([
            'license_code' => 'ARABIC01',
            'customer_name' => $name,
            'package_id' => $package->id,
            'status' => LicenseStatus::Trial,
            'expired_at' => now()->addDays(14),
            'customer_balance' => 0,
        ]);

        $this->assertSame($name, $license->fresh()->customer_name);
        $this->assertStringNotContainsString('?', $license->fresh()->customer_name);
    }
}
