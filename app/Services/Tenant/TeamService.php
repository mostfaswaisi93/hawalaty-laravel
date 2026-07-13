<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Enums\RoleKey;
use App\Models\Tenant\TeamMember;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
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
     * account, and removing the tenant's last remaining ACTIVE team member
     * who holds the `owner`-keyed system role — mirrors Angular's Team
     * component (removeMember()) guard rails, see
     * src/app/features/team/team.ts.
     *
     * The owner-count check only applies when the member being deleted is
     * themselves active — deleting an already-inactive owner never reduces
     * how many owners can actually log in. The count/delete is wrapped in a
     * transaction with lockForUpdate() so two concurrent deletes of two
     * different owners can't both read "2 active owners remain" and both
     * proceed, leaving zero.
     */
    public function delete(TeamMember $actingUser, TeamMember $member): void
    {
        if ($actingUser->id === $member->id) {
            throw ValidationException::withMessages([
                'teamMember' => 'You cannot remove your own account.',
            ]);
        }

        $member->loadMissing('role');

        DB::connection('tenant')->transaction(function () use ($member) {
            if ($member->role?->key === RoleKey::Owner && $member->active) {
                $remainingActiveOwners = TeamMember::where('role_id', $member->role_id)
                    ->where('active', true)
                    ->where('id', '!=', $member->id)
                    ->lockForUpdate()
                    ->count();

                if ($remainingActiveOwners === 0) {
                    throw ValidationException::withMessages([
                        'teamMember' => 'You cannot remove the last remaining owner.',
                    ]);
                }
            }

            $member->delete();
        });

        AuditLogger::record(AuditAction::Deleted, AuditEntityType::TeamMember, $member->name, AuditCategory::Team);
    }
}
