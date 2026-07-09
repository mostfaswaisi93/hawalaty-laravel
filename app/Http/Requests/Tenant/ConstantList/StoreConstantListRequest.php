<?php

namespace App\Http\Requests\Tenant\ConstantList;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConstantListRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated explicitly via $this->authorize('create', ConstantList::class)
        // in ConstantListController.
        return true;
    }

    public function rules(): array
    {
        return [
            // "tenant." prefix required — Rule::unique() otherwise
            // resolves against the app's DEFAULT connection (platform),
            // not the dynamic `tenant` connection. See
            // StoreExpenseRequest for the full reasoning (same bug class).
            'title' => ['required', 'string', 'max:255', Rule::unique('tenant.constant_lists', 'title')],
        ];
    }
}
