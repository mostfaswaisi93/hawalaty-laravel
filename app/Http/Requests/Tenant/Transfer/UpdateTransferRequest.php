<?php

namespace App\Http\Requests\Tenant\Transfer;

use App\Enums\TransferStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(TransferStatus::class)],
        ];
    }
}
