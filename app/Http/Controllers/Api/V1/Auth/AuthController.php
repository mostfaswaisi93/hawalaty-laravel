<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Exceptions\DeviceLimitReachedException;
use App\Exceptions\InvalidLicenseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SignupRequest;
use App\Models\License;
use App\Models\Tenant\TeamMember;
use App\Services\Tenant\AuditLogger;
use App\Services\Tenant\DeviceRegistrar;
use App\Services\TenantConnectionResolver;
use App\Services\TenantProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthController extends Controller
{
    public function __construct(private readonly TenantProvisioner $provisioner) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $license = TenantConnectionResolver::resolve($data['licenseCode']);
        } catch (InvalidLicenseException) {
            return response()->json(['message' => 'Invalid or inactive license.'], 422);
        }

        app()->instance('currentLicense', $license);

        try {
            $member = TeamMember::where('email', $data['email'])->with(['role.permissions'])->first();

            if (! $member || ! $member->active || ! Hash::check($data['password'], $member->password)) {
                return response()->json(['message' => 'Invalid credentials.'], 422);
            }

            Auth::guard('tenant')->setUser($member);
            AuditLogger::record(AuditAction::LoggedIn, AuditEntityType::TeamMember, $member->name, AuditCategory::Auth);

            return response()->json($this->tokenResponse($member, $license, $request));
        } catch (DeviceLimitReachedException $e) {
            return response()->json(['message' => $e->getMessage(), 'deviceLimit' => $e->limit], 403);
        } catch (Throwable $e) {
            Log::error('Login failed unexpectedly.', [
                'licenseCode' => $data['licenseCode'],
                'exception' => $e,
            ]);

            return response()->json(['message' => 'Invalid credentials.'], 422);
        }
    }

    public function signup(SignupRequest $request): JsonResponse
    {
        $license = $this->provisioner->provision($request->validated());

        TenantConnectionResolver::resolve($license->license_code);
        app()->instance('currentLicense', $license);

        $member = TeamMember::where('email', $request->validated()['ownerEmail'])
            ->with(['role.permissions'])
            ->firstOrFail();

        return response()->json($this->tokenResponse($member, $license), 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function tokenResponse(TeamMember $member, License $license, ?Request $request = null): array
    {
        $token = $member->createToken('api');

        try {
            app(DeviceRegistrar::class)->register($member, $token->accessToken, $request ?? request(), $license);
        } catch (DeviceLimitReachedException $e) {
            $token->accessToken->delete();

            throw $e;
        }

        return [
            'token' => $token->plainTextToken,
            'licenseCode' => $license->license_code,
            'user' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'roleId' => $member->role_id,
                'permissions' => $member->role?->permissions
                    ->pluck('key')
                    ->map(fn ($key) => $key->value)
                    ->all() ?? [],
            ],
        ];
    }
}
