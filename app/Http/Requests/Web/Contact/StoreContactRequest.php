<?php

namespace App\Http\Requests\Web\Contact;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The public contact form. Unauthenticated by definition, so the rules here are
 * the only thing standing between the inbox and a spam bot.
 */
class StoreContactRequest extends FormRequest
{
    /** Subjects the picker offers. Free text would be a spam vector for no gain. */
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
            // Digits, spaces and the usual separators. Deliberately loose: this
            // form is answered by hand, and rejecting a valid regional format
            // costs a lead.
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[\d\s()+\-]+$/'],
            'company' => ['nullable', 'string', 'max:150'],
            'subject' => ['required', Rule::in(self::SUBJECTS)],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            'plan' => ['nullable', 'string', 'max:60'],

            /*
             * Honeypot. Hidden from people by CSS and from screen readers by
             * aria-hidden + tabindex="-1", so anything that fills it in is a
             * bot walking the DOM. `prohibited` fails the request outright
             * rather than silently discarding it, which keeps the behaviour
             * testable - a bot reads a validation error, which it will not act
             * on, and a real person can never reach the field to trip it.
             */
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

    /**
     * Send a rejected submission back to the form itself.
     *
     * The form is one section of a long landing page, so the default "back to
     * the previous URL" drops the visitor at the hero with their errors two
     * screens below the fold and no indication anything happened.
     */
    protected function getRedirectUrl(): string
    {
        $url = $this->redirector->getUrlGenerator();

        return $url->previous().'#contact';
    }
}
