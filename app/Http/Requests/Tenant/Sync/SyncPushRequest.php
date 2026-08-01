<?php

namespace App\Http\Requests\Tenant\Sync;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        ];
    }

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
