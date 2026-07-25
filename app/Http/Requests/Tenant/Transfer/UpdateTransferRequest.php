<?php

namespace App\Http\Requests\Tenant\Transfer;

use App\Enums\TransferStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Deliberately narrow - the Angular app never edits order line items after
 * creation anywhere in the current UI, only the status (e.g. marking a
 * pending order completed/failed), so only `status` is accepted here.
 */
class UpdateTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated explicitly via $this->authorize('update', $transfer) in
        // TransferController.
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(TransferStatus::class)],
        ];
    }
}
