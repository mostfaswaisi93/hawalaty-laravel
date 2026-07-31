<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Contact\StoreContactRequest;
use App\Models\ContactMessage;
use App\Settings\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The landing page's "تواصل معنا" form.
 *
 * Public and unauthenticated, so it is rate limited at the route (see
 * routes/web.php) on top of the honeypot in StoreContactRequest.
 */
class ContactController extends Controller
{
    public function store(StoreContactRequest $request): RedirectResponse
    {
        $message = ContactMessage::create([
            ...$request->safe()->except('website'),
            'ip_address' => $request->ip(),
            'locale' => app()->getLocale(),
        ]);

        $this->notifySupport($message);

        return redirect()
            ->to(route('home').'#contact')
            ->with('success', __('وصلتنا رسالتك. سنردّ عليك خلال يوم عمل واحد.'));
    }

    /**
     * Best effort, deliberately.
     *
     * The row above is the record; this is a heads-up about it. Mail is the one
     * step here that depends on infrastructure outside the app - a wrong SMTP
     * credential, a provider outage, `MAIL_MAILER=log` in development - and
     * none of that is a reason to show a visitor an error page for a message
     * that was in fact saved.
     */
    private function notifySupport(ContactMessage $message): void
    {
        $to = app(PlatformSettings::class)->get('support_email');

        if (blank($to)) {
            return;
        }

        try {
            Mail::raw($this->body($message), function ($mail) use ($to, $message) {
                $mail->to($to)
                    ->subject(__('رسالة جديدة من الموقع: :subject', ['subject' => $message->subject]))
                    // Reply-To, never From: sending as the visitor's own domain
                    // is what gets a platform's mail marked as spoofed.
                    ->replyTo($message->email, $message->name);
            });
        } catch (Throwable $e) {
            Log::warning('Contact message saved but the notification mail failed.', [
                'contact_message_id' => $message->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function body(ContactMessage $message): string
    {
        return collect([
            __('الاسم').': '.$message->name,
            __('البريد الإلكتروني').': '.$message->email,
            __('رقم الهاتف').': '.($message->phone ?: '-'),
            __('اسم المشروع').': '.($message->company ?: '-'),
            __('الموضوع').': '.$message->subject,
            __('الباقة').': '.($message->plan ?: '-'),
            '',
            $message->message,
        ])->implode(PHP_EOL);
    }
}
