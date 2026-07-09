<?php

namespace App\Http\Requests\Admin\Package;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'deviceLimit' => ['sometimes', 'required', 'integer', 'min:0'],
            'features' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
