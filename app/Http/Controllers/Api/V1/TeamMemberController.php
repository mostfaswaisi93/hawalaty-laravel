<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\TeamMember\StoreTeamMemberRequest;
use App\Http\Requests\Tenant\TeamMember\UpdateTeamMemberRequest;
use App\Http\Resources\Tenant\TeamMemberResource;
use App\Models\Tenant\TeamMember;
use App\Services\Tenant\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamMemberController extends Controller
{
    public function __construct(private readonly TeamService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return TeamMemberResource::collection($this->service->list($request->query('search')));
    }

    public function store(StoreTeamMemberRequest $request): JsonResponse
    {
        $this->authorize('create', TeamMember::class);

        $member = $this->service->create($request->validated());

        return (new TeamMemberResource($member))->response()->setStatusCode(201);
    }

    public function update(UpdateTeamMemberRequest $request, string $teamMember): JsonResponse
    {
        $member = TeamMember::findOrFail($teamMember);

        $this->authorize('update', $member);

        $member = $this->service->update($member, $request->validated());

        return (new TeamMemberResource($member))->response();
    }

    public function destroy(Request $request, string $teamMember): JsonResponse
    {
        $member = TeamMember::findOrFail($teamMember);

        $this->authorize('delete', $member);

        $this->service->delete($request->user(), $member);

        return response()->json(null, 204);
    }
}
