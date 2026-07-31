<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The inbox for the landing page's contact form.
 *
 * Read and delete only - a lead is answered by email, not edited here. The
 * point of the page is that a message which arrives while the mail relay is
 * misconfigured is still reachable.
 */
class ContactMessageController extends Controller
{
    use HasPerPage;

    public function index(Request $request): View
    {
        $perPage = $this->perPage($request);

        return view('admin.contact-messages.index', [
            'messages' => ContactMessage::query()->forInbox()->paginate($perPage)->withQueryString(),
            'unreadCount' => ContactMessage::query()->unread()->count(),
            'perPage' => $perPage,
        ]);
    }

    /** Marking as read is idempotent, so re-marking an already-read row is a no-op. */
    public function markRead(string $message): RedirectResponse
    {
        $contactMessage = ContactMessage::findOrFail($message);

        if ($contactMessage->isUnread()) {
            $contactMessage->forceFill(['read_at' => now()])->save();
        }

        return back()->with('success', __('تم تعليم الرسالة كمقروءة.'));
    }

    public function destroy(string $message): RedirectResponse
    {
        ContactMessage::findOrFail($message)->delete();

        return back()->with('success', __('تم حذف الرسالة.'));
    }
}
