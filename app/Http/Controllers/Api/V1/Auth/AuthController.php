<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Exceptions\InvalidLicenseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SignupRequest;
use App\Models\License;
use App\Models\Tenant\TeamMember;
use App\Services\Tenant\AuditLogger;
use App\Services\TenantConnectionResolver;
use App\Services\TenantProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * See BACKEND_PROMPT.md sections 5 and 8.1 for the login/connection-routing
 * design this implements.
 */
class AuthController extends Controller
{
    public function __construct(private readonly TenantProvisioner $provisioner) {}

    /**
     * No token exists yet, so `licenseCode` is read straight from the
     * request body and TenantConnectionResolver is called directly here
     * (not via the tenant.header middleware, which only runs on routes that
     * already carry a Bearer token to check).
     */
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

            // AuditLogger resolves its actor from the `tenant` guard, which
            // this manual Hash::check() flow never populates (no
            // Auth::attempt() call) - set it explicitly so the login event
            // attributes to the real member instead of falling back to
            // "System".
            Auth::guard('tenant')->setUser($member);
            AuditLogger::record(AuditAction::LoggedIn, AuditEntityType::TeamMember, $member->name, AuditCategory::Auth);

            return response()->json($this->tokenResponse($member, $license));
        } catch (Throwable $e) {
            Log::error('Login failed unexpectedly.', [
                'licenseCode' => $data['licenseCode'],
                'exception' => $e,
            ]);

            // Generic message either way - never leak whether the email exists.
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }
    }

    public function signup(SignupRequest $request): JsonResponse
    {
        $license = $this->provisioner->provision($request->validated());

        // provision() already leaves the `tenant` connection pointed at the
        // new database, but resolve explicitly here too so login-after-signup
        // doesn't silently depend on that side effect.
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

    /**
     * @return array{token: string, licenseCode: string, user: array{id: string, name: string, email: string, roleId: ?string, permissions: array<int, string>}}
     */
    private function tokenResponse(TeamMember $member, License $license): array
    {
        return [
            'token' => $member->createToken('api')->plainTextToken,
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
