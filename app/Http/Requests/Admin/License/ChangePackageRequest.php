<?php

namespace App\Http\Requests\Admin\License;

use Illuminate\Foundation\Http\FormRequest;

class ChangePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'packageId' => ['required', 'string', 'exists:packages,id'],
        ];
    }
}
