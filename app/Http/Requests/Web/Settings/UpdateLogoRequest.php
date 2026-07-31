<?php

namespace App\Http\Requests\Web\Settings;

use App\Services\Tenant\BusinessLogo;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any signed-in member reaches /settings; the logo is business-wide, so
        // it is gated in the controller on the same permission as the rest of
        // the business vocabulary.
        return true;
    }

    public function rules(): array
    {
        return [
            // `image` alone would accept an SVG on some setups, which can carry
            // script - the receipt renders this inline, so the list is explicit.
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:'.BusinessLogo::MAX_KILOBYTES],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.max' => __('حجم الشعار كبير. الحد الأقصى ١ ميغابايت.'),
            'logo.mimes' => __('الصيغ المقبولة: PNG أو JPG أو WEBP.'),
        ];
    }
}
