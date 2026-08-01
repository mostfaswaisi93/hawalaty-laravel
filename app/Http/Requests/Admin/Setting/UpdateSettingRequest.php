<?php

namespace App\Http\Requests\Admin\Setting;

use App\Settings\SettingRegistry;
use App\Settings\SettingType;
use Illuminate\Foundation\Http\FormRequest;

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
