<?php

namespace App\Http\Requests\Tenant\Report;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/v1/reports?from=&to= — both required, `to` must be >= `from`.
 */
class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ];
    }
}
