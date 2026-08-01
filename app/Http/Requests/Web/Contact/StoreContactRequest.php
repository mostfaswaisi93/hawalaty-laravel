<?php

namespace App\Http\Requests\Web\Contact;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public const SUBJECTS = ['sales', 'demo', 'support', 'partnership', 'other'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],

            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[\d\s()+\-]+$/'],
            'company' => ['nullable', 'string', 'max:150'],
            'subject' => ['required', Rule::in(self::SUBJECTS)],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            'plan' => ['nullable', 'string', 'max:60'],

            'website' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('الاسم'),
            'email' => __('البريد الإلكتروني'),
            'phone' => __('رقم الهاتف'),
            'company' => __('اسم المشروع'),
            'subject' => __('الموضوع'),
            'message' => __('الرسالة'),
        ];
    }

    public function messages(): array
    {
        return [
            'website.prohibited' => __('تعذّر إرسال الرسالة. يرجى المحاولة مرة أخرى.'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        $url = $this->redirector->getUrlGenerator();

        return $url->previous().'#contact';
    }
}
