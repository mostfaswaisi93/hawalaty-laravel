<?php

namespace App\Http\Requests\Tenant\ConstantList;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What this tenant calls its catalogue. All three are optional - leaving one
 * blank falls back to the shipped default rather than showing an empty heading.
 */
class UpdateCatalogNamingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'catalogLabel' => ['nullable', 'string', 'max:40'],
            'categoryLabel' => ['nullable', 'string', 'max:24'],
            'itemLabel' => ['nullable', 'string', 'max:24'],
        ];
    }

    public function attributes(): array
    {
        return [
            'catalogLabel' => __('اسم القائمة'),
            'categoryLabel' => __('مسمّى الفئة'),
            'itemLabel' => __('مسمّى الصنف'),
        ];
    }
}
