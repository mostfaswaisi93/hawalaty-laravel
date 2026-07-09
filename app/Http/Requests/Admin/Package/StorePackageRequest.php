<?php

namespace App\Http\Requests\Admin\Package;

use Illuminate\Foundation\Http\FormRequest;

class StorePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated by the `auth:sanctum` + `admin` route middleware, not a
        // per-model Policy — see BACKEND_PROMPT.md task prompt, Admin
        // domain has no Policy layer, only the platform-staff gate.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'deviceLimit' => ['required', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
        ];
    }
}
