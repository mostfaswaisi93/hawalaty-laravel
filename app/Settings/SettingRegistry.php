<?php

namespace App\Settings;

class SettingRegistry
{
    public function groups(): array
    {
        return [
            [
                'label' => 'هوية المنصّة',
                'description' => 'الاسم وبيانات التواصل التي تظهر للعملاء',
                'icon' => 'badge',
                'settings' => [
                    [
                        'key' => 'site_name',
                        'label' => 'اسم المنصّة',
                        'description' => 'يظهر في عنوان تبويب المتصفح داخل لوحة تحكم المنصّة.',
                        'type' => SettingType::Text,
                        'default' => 'Hawalaty Platform',
                        'rules' => ['required', 'string', 'max:120'],
                        'placeholder' => 'Hawalaty Platform',
                    ],
                    [
                        'key' => 'support_email',
                        'label' => 'بريد الدعم',
                        'description' => 'يُستخدم في صفحات المساعدة والخصوصية والشروط وصفحة الهبوط.',
                        'type' => SettingType::Email,
                        'default' => 'support@hawalaty.test',
                        'rules' => ['required', 'email', 'max:255'],
                        'placeholder' => 'support@example.com',
                    ],
                    [
                        'key' => 'support_phone',
                        'label' => 'هاتف الدعم',
                        'description' => 'اختياري. يظهر في صفحة المساعدة عند تعبئته فقط.',
                        'type' => SettingType::Text,
                        'default' => null,
                        'rules' => ['nullable', 'string', 'max:40'],
                        'placeholder' => '+970 59 000 0000',
                    ],
                ],
            ],
            [
                'label' => 'التراخيص والاشتراكات',
                'description' => 'المدد الافتراضية لدورة حياة الترخيص',
                'icon' => 'key',
                'settings' => [
                    [
                        'key' => 'trial_days',
                        'label' => 'مدة التجربة',
                        'description' => 'عدد أيام التجربة الممنوحة للترخيص الجديد عند إنشائه.',
                        'type' => SettingType::Number,
                        'default' => 14,
                        'rules' => ['required', 'integer', 'min:1', 'max:365'],
                        'unit' => 'يوم',
                    ],
                    [
                        'key' => 'license_expiry_warning_days',
                        'label' => 'التنبيه قبل انتهاء الترخيص',
                        'description' => 'يظهر الترخيص في قائمة "على وشك الانتهاء" بلوحة التحكم خلال هذه المدة.',
                        'type' => SettingType::Number,
                        'default' => 30,
                        'rules' => ['required', 'integer', 'min:1', 'max:365'],
                        'unit' => 'يوم',
                    ],
                ],
            ],
        ];
    }

    public function all(): array
    {
        $flat = [];

        foreach ($this->groups() as $group) {
            foreach ($group['settings'] as $definition) {
                $flat[$definition['key']] = $definition;
            }
        }

        return $flat;
    }

    public function find(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    public function boundsFor(array $definition): array
    {
        $bounds = ['min' => null, 'max' => null];

        foreach ((array) ($definition['rules'] ?? []) as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            if ($definition['type'] !== SettingType::Number) {
                continue;
            }

            if (str_starts_with($rule, 'min:')) {
                $bounds['min'] = substr($rule, 4);
            }

            if (str_starts_with($rule, 'max:')) {
                $bounds['max'] = substr($rule, 4);
            }
        }

        return $bounds;
    }

    public function has(string $key): bool
    {
        return $this->find($key) !== null;
    }

    public function defaults(): array
    {
        return array_map(fn (array $d) => $d['default'], $this->all());
    }

    public function rules(): array
    {
        $rules = [];

        foreach ($this->all() as $key => $definition) {
            $rules["settings.{$key}"] = $definition['type'] === SettingType::Boolean
                ? ['nullable', 'boolean']
                : $definition['rules'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->all() as $key => $definition) {
            $attributes["settings.{$key}"] = __($definition['label']);
        }

        return $attributes;
    }
}
