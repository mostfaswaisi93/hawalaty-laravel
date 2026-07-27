<?php

namespace App\Services\Tenant;

use App\Exceptions\DeviceLimitReachedException;
use App\Models\License;
use App\Models\Tenant\Device;
use App\Models\Tenant\TeamMember;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Turns a freshly-issued Sanctum token into a Device row, and enforces the
 * license package's `device_limit`.
 *
 * WHY THIS EXISTS
 * `packages.device_limit` was set by the Super Admin and shown on the packages
 * page, but was never read anywhere - nothing enforced it. More basically,
 * NOTHING in the codebase ever created a Device row, so /devices could only
 * ever render its empty state and the limit had nothing to count. (The
 * `sync_events.device_id` column was even made nullable to work around the
 * absence of device registration - see that migration.)
 *
 * THE LIMIT IS LICENSE-WIDE, not per team member: it is an entitlement of the
 * subscribed package, so five members sharing a 3-device package get three
 * devices between them, not three each.
 */
class DeviceRegistrar
{
    /** Used when the package itself sets no limit (0 = unlimited). */
    private const UNLIMITED = 0;

    /**
     * Registers the device that just signed in.
     *
     * @throws DeviceLimitReachedException when the package's allowance is full
     */
    public function register(
        TeamMember $member,
        PersonalAccessToken $token,
        Request $request,
        ?License $license = null,
    ): Device {
        $limit = $this->limitFor($license);

        if ($limit !== self::UNLIMITED && $this->activeCount() >= $limit) {
            throw new DeviceLimitReachedException($limit);
        }

        $agent = (string) $request->userAgent();

        return Device::create([
            'team_member_id' => $member->id,
            'sanctum_token_id' => $token->id,
            'name' => $this->deviceName($request, $agent),
            'browser' => $this->browser($agent),
            'platform_icon' => $this->platformIcon($agent),
            'last_active_at' => now(),
        ]);
    }

    /**
     * How many device slots the license's package grants, and how many are in
     * use right now. Drives the meter on the tenant's devices page.
     *
     * @return array{used: int, limit: int, unlimited: bool, remaining: int}
     */
    public function usage(?License $license = null): array
    {
        $limit = $this->limitFor($license);
        $used = $this->activeCount();

        return [
            'used' => $used,
            'limit' => $limit,
            'unlimited' => $limit === self::UNLIMITED,
            'remaining' => $limit === self::UNLIMITED ? PHP_INT_MAX : max(0, $limit - $used),
        ];
    }

    /** Total registered devices for the whole tenant, across every member. */
    public function activeCount(): int
    {
        return Device::query()->count();
    }

    /**
     * The package allowance. `currentLicense` is bound by the tenant-session
     * middleware (web) and by the login flow (API); if neither ran, the limit
     * is treated as unlimited rather than zero - locking everyone out because
     * a container binding was missing would be the worse failure.
     */
    private function limitFor(?License $license = null): int
    {
        $license ??= app()->bound('currentLicense') ? app('currentLicense') : null;

        if (! $license) {
            return self::UNLIMITED;
        }

        return (int) ($license->package?->device_limit ?? self::UNLIMITED);
    }

    /**
     * A human label for the row. A mobile client can send `X-Device-Name`
     * (nothing else knows the handset's real name); browsers fall back to the
     * platform parsed out of the user agent.
     */
    private function deviceName(Request $request, string $agent): string
    {
        $supplied = trim((string) $request->header('X-Device-Name'));

        if ($supplied !== '') {
            return mb_substr($supplied, 0, 80);
        }

        return match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Macintosh') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => __('جهاز غير معروف'),
        };
    }

    private function browser(string $agent): string
    {
        return match (true) {
            // Edge and Opera both carry "Chrome" in their UA, so they must be
            // tested first or every browser reads as Chrome.
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox') => 'Firefox',
            str_contains($agent, 'Chrome') => 'Chrome',
            str_contains($agent, 'Safari') => 'Safari',
            $agent === '' => __('تطبيق الجوال'),
            default => __('غير معروف'),
        };
    }

    /** A Material Symbols name; the view whitelists what it will render. */
    private function platformIcon(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'iPhone') => 'phone_iphone',
            str_contains($agent, 'Android') => 'phone_android',
            str_contains($agent, 'iPad') || str_contains($agent, 'Tablet') => 'tablet',
            str_contains($agent, 'Windows') || str_contains($agent, 'Macintosh') || str_contains($agent, 'Linux') => 'computer',
            default => 'devices',
        };
    }
}
