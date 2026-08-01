<?php

namespace App\Http\Requests\Tenant\ConstantList;

use App\Services\Tenant\CatalogIcons;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What this tenant calls its catalogue, and which glyph stands for it.
 *
 * The two grammar labels stay optional - blank falls back to the shipped
 * default rather than showing an empty heading. `catalogLabel` is the one that
 * matters: naming the section is what puts it in the sidebar at all, and
 * clearing it takes it back out (see CatalogNaming::isConfigured()).
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
            // Whitelisted against the same catalogue the picker offers. The value
            // is rendered straight into a `material-symbols-outlined` span as a
            // ligature, so free text would be a way to put content on the page -
            // the same reasoning CatalogIcons documents for item icons.
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
