<?php

namespace App\Http\Requests\Admin\Expense;

use App\Http\Requests\Admin\Concerns\ChecksBoxBalance;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    use ChecksBoxBalance;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'boxId' => ['required', 'string', 'exists:boxes,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->refuseIfBoxCannotAfford($validator);
    }
}
