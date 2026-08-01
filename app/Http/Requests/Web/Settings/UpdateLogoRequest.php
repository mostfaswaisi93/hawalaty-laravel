<?php

namespace App\Http\Requests\Web\Settings;

use App\Services\Tenant\BusinessLogo;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

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
