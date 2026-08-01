<?php

namespace App\Http\Requests\Tenant\ConstantList;

use Illuminate\Foundation\Http\FormRequest;

class StoreConstantListItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'value' => ['required', 'string', 'max:255'],
        ];
    }
}
