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
