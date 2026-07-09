<?php

namespace App\Http\Requests\Admin\Box;

use Illuminate\Foundation\Http\FormRequest;

class StoreBoxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'balance' => ['sometimes', 'numeric'],
        ];
    }
}
