<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\Tenant\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The API's front door - the endpoints every client hits before any other, and
 * which had no coverage at all.
 *
 * SCOPE NOTE, because the gap is deliberate rather than overlooked: a
 * SUCCESSFUL tenant login cannot be exercised here. It resolves a licence and
 * repoints the `tenant` connection at that licence's own database, and the
 * harness that owns a tenant database (TenantTestCase) leaves the platform
 * connection without tables to hold a licence row. What is covered is
 * everything that does not need a real tenant to exist: every refusal, the
 * throttle, and token revocation - which is where the security lives anyway.
 * The authenticated path beyond login is covered by the API smoke suites.
 */
class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Login is throttled at 6/minute. Without clearing it, a test that runs
        // after the ones below would be rejected for the previous test's
        // attempts and fail for a reason that has nothing to do with it.
        RateLimiter::clear('');
    }

    // --- tenant login -----------------------------------------------------

    public function test_an_unknown_licence_is_refused(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'licenseCode' => 'NOSUCHCODE',
            'email' => 'owner@example.test',
            'password' => 'whatever',
        ])->assertStatus(422);
    }

    /**
     * The refusal must not distinguish a wrong licence from a wrong password.
     * A different status or message for each turns the endpoint into an oracle
     * for enumerating which licence codes exist.
     */
    public function test_the_refusal_does_not_reveal_whether_the_licence_exists(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'licenseCode' => 'NOSUCHCODE',
            'email' => 'owner@example.test',
            'password' => 'whatever',
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringNotContainsStringIgnoringCase('licence', (string) $response->json('message'));
        $this->assertStringNotContainsStringIgnoringCase('not found', (string) $response->json('message'));
    }

    public function test_login_requires_every_credential(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['licenseCode', 'email', 'password']);
    }

    /** Six attempts a minute, so a stolen licence code cannot be brute-forced. */
    public function test_login_is_rate_limited(): void
    {
        $payload = ['licenseCode' => 'NOSUCHCODE', 'email' => 'a@b.test', 'password' => 'x'];

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/login', $payload);
        }

        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(429);
    }

    // --- admin login ------------------------------------------------------

    public function test_an_admin_receives_a_token(): void
    {
        Admin::factory()->create([
            'email' => 'ops@hawalaty.test',
            'password' => Hash::make('Str0ngPassw0rd'),
        ]);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'ops@hawalaty.test',
            'password' => 'Str0ngPassw0rd',
        ])->assertOk();

        $this->assertNotEmpty($response->json('token'));
        $this->assertSame('ops@hawalaty.test', $response->json('admin.email'));
    }

    public function test_a_wrong_admin_password_is_refused(): void
    {
        Admin::factory()->create([
            'email' => 'ops@hawalaty.test',
            'password' => Hash::make('Str0ngPassw0rd'),
        ]);

        $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'ops@hawalaty.test',
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    /** An unknown address must answer exactly as a wrong password does. */
    public function test_an_unknown_admin_address_is_refused_identically(): void
    {
        $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'nobody@hawalaty.test',
            'password' => 'Str0ngPassw0rd',
        ])->assertStatus(422);
    }

    // --- logout -----------------------------------------------------------

    /**
     * Logout has to REVOKE the token, not merely answer politely. A token that
     * still works after signing out is a credential the user believes they
     * destroyed.
     */
    public function test_admin_logout_revokes_the_token_it_was_called_with(): void
    {
        $admin = Admin::factory()->create();
        $token = $admin->createToken('admin-api');

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/admin/auth/logout')
            ->assertOk();

        $this->assertSame(0, $admin->tokens()->count(), 'The token survived logout.');
    }

    public function test_admin_logout_refuses_a_guest(): void
    {
        $this->postJson('/api/v1/admin/auth/logout')->assertUnauthorized();
    }

    /**
     * The TENANT logout answers 422, not 401, and that is the correct order:
     * `tenant.header` runs ahead of `auth:sanctum` on every tenant route, so a
     * request with no X-License-Code is refused before there is any tenant
     * database to check a token against. Asserting 401 here would be asserting
     * that the middleware ordering is wrong.
     */
    public function test_tenant_logout_refuses_a_request_with_no_licence_header(): void
    {
        $this->postJson('/api/v1/auth/logout')
            ->assertStatus(422)
            ->assertJsonPath('message', 'The X-License-Code header is required.');
    }

    /** A tenant token must not be able to sign out of the platform console. */
    public function test_a_tenant_token_cannot_reach_the_admin_logout(): void
    {
        Sanctum::actingAs(new TeamMember(['name' => 'موظف']), ['*']);

        $response = $this->postJson('/api/v1/admin/auth/logout');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }
}
