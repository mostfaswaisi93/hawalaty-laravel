<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\ResolveTenantConnection;
use App\Models\Tenant\TeamMember;
use Laravel\Sanctum\Sanctum;
use Tests\TenantTestCase;

abstract class TenantApiTestCase extends TenantTestCase
{
    protected array $roles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ResolveTenantConnection::class);

        $this->roles = $this->seedStandardRoles();
    }

    protected function actAsOwner(): TeamMember
    {
        $owner = TeamMember::factory()->create(['role_id' => $this->roles['owner']->id]);

        Sanctum::actingAs($owner, ['*']);

        return $owner;
    }
}
