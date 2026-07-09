<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Enums\RoleKey;
use App\Models\Tenant\TeamMember;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class TeamService
{
    public function list(?string $search = null): Collection
    {
        $query = TeamMember::query()->with('role');

        if ($search) {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    public function create(array $data): TeamMember
    {
        $member = TeamMember::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role_id' => $data['roleId'],
            'active' => $data['active'] ?? true,
        ]);

        $member->load('role');

        AuditLogger::record(AuditAction::Created, AuditEntityType::TeamMember, $member->name, AuditCategory::Team);

        return $member;
    }

    public function update(TeamMember $member, array $data): TeamMember
    {
        $update = [];

        foreach (['name', 'email'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        if (array_key_exists('roleId', $data)) {
            $update['role_id'] = $data['roleId'];
        }
        if (array_key_exists('active', $data)) {
            $update['active'] = $data['active'];
        }
        // Optional-if-present: an empty/omitted password means "keep the
        // current one".
        if (! empty($data['password'])) {
            $update['password'] = $data['password'];
        }

        $member->update($update);
        $member->load('role');

        AuditLogger::record(AuditAction::Updated, AuditEntityType::TeamMember, $member->name, AuditCategory::Team);

        return $member;
    }

    /**
     * BLOCKS: a team member removing their own currently-authenticated
     * account, and removing the tenant's last remaining team member who
     * holds the `owner`-keyed system role — mirrors Angular's Team
     * component (removeMember()) guard rails, see
     * src/app/features/team/team.ts.
     */
    public function delete(TeamMember $actingUser, TeamMember $member): void
    {
        if ($actingUser->id === $member->id) {
            throw ValidationException::withMessages([
                'teamMember' => 'You cannot remove your own account.',
            ]);
        }

        $member->loadMissing('role');

        if ($member->role?->key === RoleKey::Owner) {
            $remainingOwners = TeamMember::where('role_id', $member->role_id)->count();

            if ($remainingOwners <= 1) {
                throw ValidationException::withMessages([
                    'teamMember' => 'You cannot remove the last remaining owner.',
                ]);
            }
        }

        $name = $member->name;
        $member->delete();

        AuditLogger::record(AuditAction::Deleted, AuditEntityType::TeamMember, $name, AuditCategory::Team);
    }
}
