<?php

namespace App\Http\Requests\Tenant\Sync;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/sync/push envelope validation. Narrowed for this first
 * version to `transfer`/`expense` entity types and `create`-only
 * operations - see SyncService's docblock and BACKEND_PROMPT.md section 10.
 * Each event's `payload` shape is validated separately, per-event, inside
 * SyncService (reusing StoreTransferRequest/StoreExpenseRequest's own
 * rules()), not here.
 */
class SyncPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1'],
            'events.*.clientUuid' => ['required', 'string', 'max:255'],
            'events.*.entityType' => ['required', Rule::in(['transfer', 'expense'])],
            'events.*.operation' => ['required', Rule::in(['create'])],
            'events.*.payload' => ['required', 'array'],
        ];
    }
}
