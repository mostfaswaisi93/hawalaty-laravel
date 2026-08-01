<?php

namespace App\Http\Requests\Tenant\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWhatsAppSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['nullable', 'boolean'],
            'driver' => ['required', Rule::in(['log', 'cloud', 'null'])],

            'token' => ['nullable', 'string', 'max:500'],
            'appSecret' => ['nullable', 'string', 'max:255'],

            'phoneNumberId' => ['nullable', 'string', 'max:64', 'regex:/^\d*$/'],
            'wabaId' => ['nullable', 'string', 'max:64', 'regex:/^\d*$/'],

            'verifyToken' => ['nullable', 'string', 'max:255'],

            'templateNewOrder' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_]*$/'],
            'templateLanguage' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z_]*$/'],
        ];
    }

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
