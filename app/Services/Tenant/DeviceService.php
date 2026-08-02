<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Models\Tenant\Device;
use App\Models\Tenant\TeamMember;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceService
{
    public function list(TeamMember $user, ?PersonalAccessToken $currentToken): Collection
    {
        return Device::where('team_member_id', $user->id)
            ->latest('last_active_at')
            ->get()
            ->each(function (Device $device) use ($currentToken) {
                $device->is_current = $device->isCurrent($currentToken);
            });
    }

    public function listForTenant(?PersonalAccessToken $currentToken): Collection
    {
        return Device::with('teamMember')
            ->latest('last_active_at')
            ->get()
            ->each(function (Device $device) use ($currentToken) {
                $device->is_current = $device->isCurrent($currentToken);
            });
    }

    public function revoke(Device $device): void
    {
        $name = $device->name;

        if ($device->isWeb()) {
            $this->killSession($device->session_id);
        } else {
            $device->teamMember?->tokens()->where('id', $device->sanctum_token_id)->delete();
        }

        $device->delete();

        AuditLogger::record(AuditAction::SignedOut, AuditEntityType::Device, $name, AuditCategory::Devices);
    }

    /**
     * Ends a browser session for real, rather than only dropping its row.
     *
     * Deleting the Device alone would remove the entry from the page while the
     * revoked browser stayed signed in - the opposite of what the button
     * promises. Only the `database` session driver stores a row this can reach;
     * on any other driver the record is removed and the session expires on its
     * own schedule.
     *
     * The sessions table lives on the DEFAULT connection, not `tenant`.
     */
    private function killSession(string $sessionId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->delete();
    }
}
