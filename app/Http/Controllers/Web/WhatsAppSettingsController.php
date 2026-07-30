<?php

namespace App\Http\Controllers\Web;

use App\Enums\PackageFeature;
use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Http\Requests\Tenant\WhatsApp\UpdateWhatsAppSettingsRequest;
use App\Services\WhatsApp\NewOrderMessage;
use App\Services\WhatsApp\PhoneNumber;
use App\Services\WhatsApp\WhatsAppSender;
use App\Services\WhatsApp\WhatsAppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Where a tenant enters its OWN Meta Cloud API credentials.
 *
 * Gated on ConstantsManage rather than a new permission key: the tenant
 * permission set is fixed at seven (App\Enums\PermissionKey) and mirrored in the
 * Angular client, so adding an eighth would desynchronise them. ConstantsManage
 * is already "configure how this business behaves", which is what this is.
 */
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

        // Secrets are write-only: the form shows bullets, so a blank field means
        // "leave the stored value alone" - not "clear it".
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

    /**
     * Sends one message to a number the operator types, so the credentials can
     * be proven from the settings screen instead of by creating a real order.
     */
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

        // نص فارغ يعني «جرّب الإعدادات»، لا «أرسل رسالة فارغة». الإرسال يستهدف
        // نفس القالب متعدّد المتغيّرات الذي تستخدمه إشعارات الطلبات، فنزوّده بقيم
        // تجريبية بنفس العدد؛ النص المُدخَل - إن وُجد - يظهر في خانة الطلب.
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

    /**
     * Two independent gates, both required:
     *
     * - the PACKAGE must sell the capability (WhatsApp is a paid add-on, so a
     *   Basic tenant must not reach the form at all);
     * - the USER must be allowed to configure the business.
     *
     * The license is bound by ResolveTenantConnectionFromSession. If it is
     * missing the gate closes - an unresolvable package must never read as
     * "everything included".
     */
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
