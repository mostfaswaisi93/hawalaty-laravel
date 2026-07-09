<?php

namespace App\Http\Requests\Tenant\ConstantList;

use Illuminate\Foundation\Http\FormRequest;

class StoreConstantListItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated explicitly via $this->authorize('update', $constantList) in
        // ConstantListController::storeItem() — adding an item to a list is
        // treated as managing that list.
        return true;
    }

    public function rules(): array
    {
        return [
            'value' => ['required', 'string', 'max:255'],
        ];
    }
}
