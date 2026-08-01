<?php

namespace App\Services\Tenant;

use App\Exceptions\DeviceLimitReachedException;
use App\Models\License;
use App\Models\Tenant\Device;
use App\Models\Tenant\TeamMember;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceRegistrar
{
    private const UNLIMITED = 0;

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

    public function activeCount(): int
    {
        return Device::query()->count();
    }

    private function limitFor(?License $license = null): int
    {
        $license ??= app()->bound('currentLicense') ? app('currentLicense') : null;

        if (! $license) {
            return self::UNLIMITED;
        }

        return (int) ($license->package?->device_limit ?? self::UNLIMITED);
    }

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
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox') => 'Firefox',
            str_contains($agent, 'Chrome') => 'Chrome',
            str_contains($agent, 'Safari') => 'Safari',
            $agent === '' => __('تطبيق الجوال'),
            default => __('غير معروف'),
        };
    }

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
