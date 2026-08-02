<?php

namespace App\Http\Requests\Admin\License;

use App\Enums\BusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'businessName' => ['required', 'string', 'max:255'],
            'ownerName' => ['required', 'string', 'max:255'],
            'ownerEmail' => ['required', 'email', 'max:255'],

            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
            'packageId' => ['nullable', 'string', 'exists:packages,id'],
            'businessType' => ['required', Rule::enum(BusinessType::class)],

            'dbName' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            'dbUsername' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            'dbPassword' => ['required', 'string', 'max:255'],
            'dbHost' => ['nullable', 'string', 'max:255'],
            'dbPort' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return [
            'dbName.regex' => __('اسم قاعدة البيانات: حروف إنجليزية وأرقام وشرطة سفلية فقط.'),
            'dbUsername.regex' => __('اسم المستخدم: حروف إنجليزية وأرقام وشرطة سفلية فقط.'),
            'businessType.required' => __('اختر تصنيف النشاط - هو الذي يحدّد الوحدات التي تُفتح لهذه النسخة.'),
        ];
    }
}
