<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Backs the header bell-icon notification dropdown (layouts/app.blade.php).
 * TeamMember already uses Notifiable, so ->notifications()/->unreadNotifications()
 * come from the framework for free.
 */
class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $member = auth('tenant')->user();

        $notifications = $member->notifications()->latest()->take(10)->get()->map(fn ($n) => [
            'id' => $n->id,
            'data' => $n->data,
            'readAt' => $n->read_at,
            'createdAt' => $n->created_at->diffForHumans(),
        ]);

        return response()->json([
            'notifications' => $notifications,
            'unreadCount' => $member->unreadNotifications()->count(),
        ]);
    }

    public function markRead(string $notification): JsonResponse
    {
        $member = auth('tenant')->user();
        $member->notifications()->where('id', $notification)->first()?->markAsRead();

        return response()->json(['unreadCount' => $member->unreadNotifications()->count()]);
    }

    public function markAllRead(): JsonResponse|RedirectResponse
    {
        auth('tenant')->user()->unreadNotifications->markAsRead();

        if (request()->wantsJson()) {
            return response()->json(['unreadCount' => 0]);
        }

        return back();
    }
}
