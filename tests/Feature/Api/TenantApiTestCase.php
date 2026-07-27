<?php

namespace Tests\Feature\Api;

use App\Models\Tenant\Role;
use App\Models\Tenant\TeamMember;
use Laravel\Sanctum\Sanctum;
use Tests\TenantTestCase;

/**
 * Base for tenant-space API tests.
 *
 * `tenant.header` normally resolves the connection from the X-License-Code
 * header, but TenantTestCase has already pointed the `tenant` connection at the
 * disposable test database - and the middleware passes straight through when no
 * header is present, leaving that in place. So acting as a member is enough; no
 * header juggling is needed here.
 */
abstract class TenantApiTestCase extends TenantTestCase
{
    /** @var array{owner: Role, manager: Role, staff: Role} */
    protected array $roles;

    protected function setUp(): void
    {
        parent::setUp();

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
