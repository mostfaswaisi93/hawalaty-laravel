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
            // Omitted/null = a backup of the platform database itself.
            'licenseId' => ['nullable', 'string', 'exists:licenses,id'],
        ];
    }
}
