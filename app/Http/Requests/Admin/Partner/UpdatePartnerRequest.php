<?php

namespace App\Http\Requests\Admin\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'percentage' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
