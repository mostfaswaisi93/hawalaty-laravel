<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Enums\RoleKey;
use App\Models\Tenant\TeamMember;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamService
{
    public function list(?string $search = null): Collection
    {
        return $this->query($search)->get();
    }

    public function paginate(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->query($search)->paginate($perPage)->withQueryString();
    }

    private function query(?string $search = null): Builder
    {
        return TeamMember::query()->with('role')->whereSearch(['name', 'email'], $search);
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

        if (! empty($data['password'])) {
            $update['password'] = $data['password'];
        }

        $member->update($update);
        $member->load('role');

        AuditLogger::record(AuditAction::Updated, AuditEntityType::TeamMember, $member->name, AuditCategory::Team);

        return $member;
    }

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
