<?php

namespace App\Http\Requests\Admin\Setting;

use App\Settings\SettingRegistry;
use App\Settings\SettingType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /api/v1/admin/settings/{key}.
 *
 * A registered key is validated with ITS OWN rules, not as a loose string.
 * Without this the API is a hole straight through the typed settings form:
 * `trial_days` could be set to "abc", which the reader would cast to 0 and hand
 * to `now()->addDays(0)` - silently issuing trials that expire immediately.
 */
class UpdateSettingRequest extends FormRequest
{
    public function __construct(private readonly SettingRegistry $registry)
    {
        parent::__construct();
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $definition = $this->registry->find((string) $this->route('key'));

        if (! $definition) {
            return ['value' => ['nullable', 'string']];
        }

        return [
            'value' => $definition['type'] === SettingType::Boolean
                ? ['nullable', 'boolean']
                : $definition['rules'],
        ];
    }

    public function attributes(): array
    {
        $definition = $this->registry->find((string) $this->route('key'));

        return $definition ? ['value' => __($definition['label'])] : [];
    }
}
