<?php

namespace App\Http\Requests\Tenant\MenuItem;

use Illuminate\Foundation\Http\FormRequest;

class ImportMenuItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated explicitly via canManage() in MenuController.
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }
}
