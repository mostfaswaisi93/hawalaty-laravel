<?php

namespace App\Http\Requests\Admin\Settlement;

use Illuminate\Foundation\Http\FormRequest;

class StoreSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'periodTo' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'confirm' => ['nullable', 'boolean'],
        ];
    }
}
