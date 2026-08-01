<?php

namespace App\Http\Requests\Tenant\ConstantList;

use App\Services\Tenant\CatalogIcons;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

            'catalogIcon' => ['nullable', 'string', Rule::in(app(CatalogIcons::class)->all())],
        ];
    }

    public function attributes(): array
    {
        return [
            'catalogLabel' => __('اسم القائمة'),
            'categoryLabel' => __('مسمّى الفئة'),
            'itemLabel' => __('مسمّى الصنف'),
            'catalogIcon' => __('أيقونة القائمة'),
        ];
    }
}
