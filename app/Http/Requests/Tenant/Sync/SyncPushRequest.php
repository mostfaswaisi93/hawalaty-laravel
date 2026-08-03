<?php

namespace App\Http\Requests\Tenant\Sync;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class SyncPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Always answer a machine.
     *
     * This request backs two mountings - the API for devices and the web route
     * for the browser client - and both callers are code, not a browser with a
     * form to send back to. Without this the web mounting falls through to the
     * default redirect-with-errors, and the queue on the other end receives an
     * HTML page it cannot read: the events look rejected for no stated reason
     * and get retried forever.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => __('تعذّر قبول بعض الأحداث.'),
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }

    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1'],
            'events.*.clientUuid' => ['required', 'string', 'max:255'],
            'events.*.entityType' => ['required', Rule::in(self::ENTITIES)],
            'events.*.operation' => ['required', Rule::in(['create', 'update', 'delete'])],

            'events.*.payload' => ['required', 'array'],
        ];
    }

    /**
     * What a disconnected device may have produced.
     *
     * @var list<string>
     */
    public const ENTITIES = ['transfer', 'expense', 'sale_return', 'warranty_claim'];

    /**
     * The ones that only make sense as a creation.
     *
     * A return arrives as a draft and is completed here; amending or deleting it
     * from a replayed event would skip the review the draft exists for. A
     * warranty claim is the record of someone walking in - it is resolved on the
     * counter, not re-sent.
     *
     * @var list<string>
     */
    private const CREATE_ONLY = ['sale_return', 'warranty_claim'];

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('events', []) as $i => $event) {
                $entity = $event['entityType'] ?? null;
                $operation = $event['operation'] ?? null;

                if (in_array($entity, self::CREATE_ONLY, true) && $operation !== 'create') {
                    $validator->errors()->add(
                        "events.{$i}.operation",
                        __('هذا النوع يُنشأ فقط من الأجهزة، ويُراجَع ويُنفَّذ من النظام.'),
                    );

                    continue;
                }

                if (! in_array($operation, ['update', 'delete'], true)) {
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
