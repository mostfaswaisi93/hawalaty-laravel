<?php

namespace App\Http\Requests\Tenant\ConstantList;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConstantListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'title' => ['required', 'string', 'max:255', Rule::unique('tenant.constant_lists', 'title')],
        ];
    }
}
