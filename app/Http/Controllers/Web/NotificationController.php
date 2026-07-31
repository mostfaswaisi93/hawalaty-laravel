<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Backs BOTH the header bell-icon dropdown (JSON) and the full notifications
 * page (Blade). The bell fetches `/notifications` with `Accept: application/json`
 * (see resources/js/app.js), so the same route serves the compact JSON feed to
 * the bell and the full paginated page to a normal browser visit - no separate
 * route, and the bell's fetch is untouched.
 *
 * TeamMember already uses Notifiable, so ->notifications()/->unreadNotifications()
 * come from the framework for free.
 */
class NotificationController extends Controller
{
    /** How many the bell peeks at; the full page paginates instead. */
    private const BELL_LIMIT = 10;

    public function index(Request $request): JsonResponse|View
    {
        $member = auth('tenant')->user();

        if ($request->wantsJson()) {
            $notifications = $member->notifications()->latest()->take(self::BELL_LIMIT)->get()->map(fn ($n) => [
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

        return view('notifications.index', [
            'notifications' => $member->notifications()->latest()->paginate(20),
            'unreadCount' => $member->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse|RedirectResponse
    {
        $member = auth('tenant')->user();
        $member->notifications()->where('id', $notification)->first()?->markAsRead();

        if ($request->wantsJson()) {
            return response()->json(['unreadCount' => $member->unreadNotifications()->count()]);
        }

        return back();
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
