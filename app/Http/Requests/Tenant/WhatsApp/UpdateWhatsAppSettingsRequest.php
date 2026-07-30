<?php

namespace App\Http\Requests\Tenant\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWhatsAppSettingsRequest extends FormRequest
{
    /** Authorised by the controller against PermissionKey::ConstantsManage. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'enabled' => ['nullable', 'boolean'],
            'driver' => ['required', Rule::in(['log', 'cloud', 'null'])],

            // Secrets arrive blank when the operator did not retype them - the
            // form renders bullets, never the stored value - so an empty string
            // means "keep what is saved", handled in the controller.
            'token' => ['nullable', 'string', 'max:500'],
            'appSecret' => ['nullable', 'string', 'max:255'],

            'phoneNumberId' => ['nullable', 'string', 'max:64', 'regex:/^\d*$/'],
            'wabaId' => ['nullable', 'string', 'max:64', 'regex:/^\d*$/'],

            // Meta generates the code Meta sends back; we only need it to match
            // what the operator typed into the Meta console.
            'verifyToken' => ['nullable', 'string', 'max:255'],

            // Template names are lowercase snake_case at Meta; rejecting other
            // shapes here turns a silent 132001 at send time into a form error.
            'templateNewOrder' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_]*$/'],
            'templateLanguage' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z_]*$/'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'driver' => __('وضع الإرسال'),
            'token' => __('رمز الوصول'),
            'appSecret' => __('سر التطبيق'),
            'phoneNumberId' => __('معرّف الرقم'),
            'wabaId' => __('معرّف حساب الأعمال'),
            'verifyToken' => __('رمز التحقق'),
            'templateNewOrder' => __('اسم القالب'),
            'templateLanguage' => __('لغة القالب'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phoneNumberId.regex' => __('معرّف الرقم أرقام فقط.'),
            'wabaId.regex' => __('معرّف حساب الأعمال أرقام فقط.'),
            'templateNewOrder.regex' => __('اسم القالب حروف إنجليزية صغيرة وأرقام وشرطة سفلية فقط.'),
            'templateLanguage.regex' => __('رمز اللغة مثل ar أو en_US.'),
        ];
    }
}
