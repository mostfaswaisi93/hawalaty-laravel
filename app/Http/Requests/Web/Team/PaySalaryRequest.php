<?php

namespace App\Http\Requests\Web\Team;

use App\Enums\SalaryPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaySalaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            // The account the money leaves. Required: "paid from where" is the
            // whole point of recording this rather than a bare note.
            'accountId' => ['required', 'string', 'exists:tenant.accounts,id'],
            // Never in the future - a salary cannot be paid before it happens,
            // and a mistyped year would silently skew every report.
            'paidFor' => ['required', 'date', 'before_or_equal:today'],
            'period' => ['nullable', Rule::enum(SalaryPeriod::class)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => __('المبلغ'),
            'accountId' => __('الحساب'),
            'paidFor' => __('عن فترة'),
            'notes' => __('ملاحظات'),
        ];
    }
}
