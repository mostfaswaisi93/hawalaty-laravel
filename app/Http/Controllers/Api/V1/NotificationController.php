<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private const MAX_PER_PAGE = 50;

    public function index(Request $request): JsonResponse
    {
        $member = $request->user();
        $perPage = min((int) $request->query('per_page', 20) ?: 20, self::MAX_PER_PAGE);

        $notifications = $member->notifications()->latest()->paginate($perPage);

        return response()->json([
            'data' => collect($notifications->items())->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'data' => $n->data,
                'readAt' => $n->read_at?->toISOString(),
                'createdAt' => $n->created_at->toISOString(),
            ]),
            'meta' => [
                'currentPage' => $notifications->currentPage(),
                'lastPage' => $notifications->lastPage(),
                'total' => $notifications->total(),
                'unreadCount' => $member->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $member = $request->user();

        $found = $member->notifications()->where('id', $notification)->first();

        abort_if($found === null, 404);

        $found->markAsRead();

        return response()->json(['unreadCount' => $member->unreadNotifications()->count()]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['unreadCount' => 0]);
    }
}
