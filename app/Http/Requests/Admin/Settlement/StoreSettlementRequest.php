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
            // The open period's start is derived server-side from the last
            // confirmed run, never sent by the client - only the closing date
            // is a choice. `before_or_equal:today` stops a run being dated into
            // the future, which would close a period that has not happened yet.
            'periodTo' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'confirm' => ['nullable', 'boolean'],
        ];
    }
}
