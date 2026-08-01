<?php

namespace App\Http\Requests\Admin\Backup;

use Illuminate\Foundation\Http\FormRequest;

class StoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'licenseId' => ['nullable', 'string', 'exists:licenses,id'],
        ];
    }
}
