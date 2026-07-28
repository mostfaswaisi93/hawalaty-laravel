<?php

namespace App\Http\Requests\Tenant\Sync;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/sync/push envelope validation.
 *
 * Entity types stay `transfer` and `expense` - those are the two records staff
 * actually create with no signal - but all three operations are now accepted.
 * Create-only meant a client could record an order offline and then had no way
 * to correct or cancel it, which is exactly when a correction is most likely.
 *
 * An update or delete must say WHICH record it means, in one of two ways:
 *   - `payload.id`         a server id, for a record the client pulled;
 *   - `payload.clientUuid` the uuid of the create event that made it, for a
 *                          record created offline whose server id the client
 *                          has not seen yet.
 * The second is what makes create-then-edit-then-sync work at all.
 *
 * Each event's own payload shape is validated per-event inside SyncService,
 * reusing the Store/Update request rules rather than restating them here.
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
            'events.*.operation' => ['required', Rule::in(['create', 'update', 'delete'])],
            'events.*.payload' => ['required', 'array'],
            'events.*.payload.id' => ['nullable', 'string'],
            'events.*.payload.clientUuid' => ['nullable', 'string'],
        ];
    }

    /**
     * "One of these two, for these operations only" is not expressible with
     * required_if/required_without alone - required_if on both would demand
     * both - so the either/or is checked here.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('events', []) as $i => $event) {
                if (! in_array($event['operation'] ?? null, ['update', 'delete'], true)) {
                    continue;
                }

                if (filled($event['payload']['id'] ?? null) || filled($event['payload']['clientUuid'] ?? null)) {
                    continue;
                }

                $validator->errors()->add(
                    "events.{$i}.payload",
                    __('يجب تحديد السجل المقصود عبر id أو clientUuid.'),
                );
            }
        });
    }
}
