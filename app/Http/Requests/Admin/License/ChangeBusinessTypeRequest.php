<?php

namespace App\Http\Requests\Admin\License;

use App\Enums\BusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeBusinessTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'businessType' => ['required', Rule::enum(BusinessType::class)],
        ];
    }
}
