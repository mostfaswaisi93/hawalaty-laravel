<?php

namespace App\Http\Requests\Admin\Setting;

use App\Settings\SettingRegistry;
use App\Settings\SettingType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function __construct(private readonly SettingRegistry $registry)
    {
        parent::__construct();
    }

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $settings = (array) $this->input('settings', []);

        foreach ($this->registry->all() as $key => $definition) {
            if ($definition['type'] === SettingType::Boolean) {
                $settings[$key] = filter_var($settings[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
        }

        $this->merge(['settings' => $settings]);
    }

    public function rules(): array
    {
        return ['settings' => ['required', 'array']] + $this->registry->rules();
    }

    public function attributes(): array
    {
        return $this->registry->attributes();
    }
}
