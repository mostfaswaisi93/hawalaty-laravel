<?php

namespace App\Http\Controllers\Web;

use App\Enums\PackageFeature;
use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\WhatsApp\UpdateWhatsAppSettingsRequest;
use App\Models\License;
use App\Services\WhatsApp\NewOrderMessage;
use App\Services\WhatsApp\PhoneNumber;
use App\Services\WhatsApp\WhatsAppSender;
use App\Services\WhatsApp\WhatsAppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppSettingsController extends Controller
{
    public function __construct(
        private readonly WhatsAppSettings $settings,
        private readonly WhatsAppSender $sender,
    ) {}

    public function index(): View
    {
        abort_unless($this->canManage(), 403);

        return view('whatsapp.settings', [
            'values' => $this->settings->masked(),
            'configured' => $this->settings->isConfigured(),
            'effectiveDriver' => $this->settings->driver(),
            'webhookUrl' => url('/webhooks/whatsapp'),
            'recipients' => $this->settings->testRecipients(),
        ]);
    }

    public function update(UpdateWhatsAppSettingsRequest $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validated();

        $this->settings->put([
            WhatsAppSettings::ENABLED => $request->boolean('enabled') ? '1' : '0',
            WhatsAppSettings::DRIVER => $data['driver'],
            WhatsAppSettings::PHONE_NUMBER_ID => $data['phoneNumberId'] ?? null,
            WhatsAppSettings::WABA_ID => $data['wabaId'] ?? null,
            WhatsAppSettings::VERIFY_TOKEN => $data['verifyToken'] ?? null,
            WhatsAppSettings::TEMPLATE_NEW_ORDER => $data['templateNewOrder'] ?? null,
            WhatsAppSettings::TEMPLATE_LANGUAGE => $data['templateLanguage'] ?? null,
        ]);

        $secrets = array_filter([
            WhatsAppSettings::TOKEN => $data['token'] ?? null,
            WhatsAppSettings::APP_SECRET => $data['appSecret'] ?? null,
        ], fn ($value) => filled($value));

        if ($secrets !== []) {
            $this->settings->put($secrets);
        }

        return redirect()
            ->route('whatsapp.settings')
            ->with('success', __('تم حفظ إعدادات واتساب.'));
    }

    public function test(Request $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $validated = $request->validate(
            [
                'phone' => ['required', 'string', 'max:32'],
                'body' => ['nullable', 'string', 'max:1000'],
            ],
            [],
            ['phone' => __('رقم المستلم'), 'body' => __('نص الرسالة')],
        );

        if (PhoneNumber::toE164($validated['phone']) === null) {
            return back()->withInput()->with('error', __('الرقم غير صالح.'));
        }

        $body = trim((string) ($validated['body'] ?? ''));

        $result = $this->sender->send(
            $validated['phone'],
            NewOrderMessage::testParameters($body !== '' ? $body : null, $this->settings->variableCount()),
        );

        if ($result->sent) {
            return back()->with('success', __('تم الإرسال. المعرّف: :id', ['id' => $result->providerMessageId ?? '-']));
        }

        if ($result->skipped) {
            return back()->with('error', __('لم تُرسل الرسالة: :reason', ['reason' => $result->errorCode ?? '-']));
        }

        return back()->with('error', __('فشل الإرسال (:code): :message', [
            'code' => $result->errorCode ?? '-',
            'message' => $result->errorMessage ?? '-',
        ]));
    }

    private function canManage(): bool
    {
        if (! $this->packageAllows()) {
            return false;
        }

        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::ConstantsManage);
    }

    private function packageAllows(): bool
    {
        $license = app()->bound('currentLicense') ? app('currentLicense') : null;

        return $license instanceof License && $license->hasFeature(PackageFeature::WhatsApp);
    }
}
