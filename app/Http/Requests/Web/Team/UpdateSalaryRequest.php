<?php

namespace App\Http\Requests\Web\Team;

use App\Enums\SalaryPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sets a member's agreed pay RATE. This is only a default for the payment form -
 * changing it never rewrites a salary already paid, because each payment stores
 * its own amount and period.
 */
class UpdateSalaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Amount and period travel together: a rate with only one half set is
     * unusable, so each requires the other rather than both being independently
     * nullable.
     */
    public function rules(): array
    {
        return [
            'salaryAmount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'required_with:salaryPeriod'],
            'salaryPeriod' => ['nullable', Rule::enum(SalaryPeriod::class), 'required_with:salaryAmount'],
        ];
    }

    public function attributes(): array
    {
        return [
            'salaryAmount' => __('الراتب'),
            'salaryPeriod' => __('الدورة'),
        ];
    }
}
