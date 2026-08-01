<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\Tenant\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('');
    }

    public function test_an_unknown_licence_is_refused(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'licenseCode' => 'NOSUCHCODE',
            'email' => 'owner@example.test',
            'password' => 'whatever',
        ])->assertStatus(422);
    }

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

    public function test_login_is_rate_limited(): void
    {
        $payload = ['licenseCode' => 'NOSUCHCODE', 'email' => 'a@b.test', 'password' => 'x'];

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/login', $payload);
        }

        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(429);
    }

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

    public function test_an_unknown_admin_address_is_refused_identically(): void
    {
        $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'nobody@hawalaty.test',
            'password' => 'Str0ngPassw0rd',
        ])->assertStatus(422);
    }

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

    public function test_tenant_logout_refuses_a_request_with_no_licence_header(): void
    {
        $this->postJson('/api/v1/auth/logout')
            ->assertStatus(422)
            ->assertJsonPath('message', 'The X-License-Code header is required.');
    }

    public function test_a_tenant_token_cannot_reach_the_admin_logout(): void
    {
        Sanctum::actingAs(new TeamMember(['name' => 'موظف']), ['*']);

        $response = $this->postJson('/api/v1/admin/auth/logout');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }
}
