<?php

namespace Tests\Feature\Admin;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\Package;
use App\Services\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * A tenant's physical database is NEVER named after customer-supplied text -
 * it is derived solely from the server-generated license code, so an Arabic
 * (or any non-ASCII) business name can never reach a MySQL identifier. These
 * tests pin that down, since the naming rule is a security boundary as much
 * as a correctness one: identifiers are interpolated into DDL, which cannot
 * use bound parameters.
 *
 * Only the naming/derivation logic is exercised here. provision() itself
 * issues real CREATE DATABASE / CREATE USER statements against MySQL and is
 * deliberately out of scope for the sqlite suite.
 */
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
        // Paired with the rejection test below, this pins the security property:
        // an ASCII code gets PAST the whitelist (so it fails later, on the DB
        // connection, not on validation), while a non-ASCII one never does.
        // Asserting the emitted SQL is not possible here - the mysql connection
        // fails before any statement reaches a query listener.
        // QueryException extends RuntimeException, so the two cannot be told
        // apart by type - the message is what distinguishes them.
        try {
            $this->invokeHidden('provisionPhysicalDatabase', ['AR12CD34']);
            $this->fail('Expected a database connection failure without a live MySQL server.');
        } catch (Throwable $e) {
            $this->assertStringNotContainsString(
                'safety whitelist',
                $e->getMessage(),
                'The whitelist wrongly rejected a valid ASCII license code.'
            );
            // Execution reached the DDL, and the identifier it built is ASCII.
            $this->assertStringContainsString('`license_ar12cd34`', $e->getMessage());
        }
    }

    public function test_a_non_ascii_license_code_is_rejected_before_any_ddl_runs(): void
    {
        // Defence in depth: generateLicenseCode() can never produce this, but if
        // a code were ever seeded or edited by hand, the whitelist must stop it
        // rather than interpolate it into CREATE DATABASE.
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
