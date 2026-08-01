<?php

namespace App\Http\Requests\Web\Team;

use App\Enums\SalaryPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
