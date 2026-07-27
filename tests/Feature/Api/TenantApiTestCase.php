<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\ResolveTenantConnection;
use App\Models\Tenant\Role;
use App\Models\Tenant\TeamMember;
use Laravel\Sanctum\Sanctum;
use Tests\TenantTestCase;

/**
 * Base for tenant-space API tests.
 *
 * `tenant.header` is disabled here, deliberately. Its whole job is to read
 * X-License-Code and repoint the `tenant` connection at that license's physical
 * database - but TenantTestCase has already pointed that connection at the
 * disposable test database, and letting the middleware run would send every
 * query at a real tenant instead.
 *
 * It is NOT left untested: TenantApiCoverageTest::test_the_license_header_is_
 * required exercises it with the middleware on, which is the behaviour that
 * actually matters (a request without the header is refused).
 */
abstract class TenantApiTestCase extends TenantTestCase
{
    /** @var array{owner: Role, manager: Role, staff: Role} */
    protected array $roles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ResolveTenantConnection::class);

        // Seeded once per test: the 7 permissions are UNIQUE by key.
        $this->roles = $this->seedStandardRoles();
    }

    /** Signs in a member holding every permission. */
    protected function actAsOwner(): TeamMember
    {
        $owner = TeamMember::factory()->create(['role_id' => $this->roles['owner']->id]);

        Sanctum::actingAs($owner, ['*']);

        return $owner;
    }
}
