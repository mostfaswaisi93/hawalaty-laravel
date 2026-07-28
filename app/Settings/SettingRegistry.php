<?php

namespace App\Settings;

/**
 * The platform settings the app actually understands.
 *
 * Before this existed, /admin/settings was a bare key/value table: an admin had
 * to already know that a key called `site_name` was meaningful, type it by
 * hand, and hope something read it. Nothing did - not one setting was consumed
 * anywhere in the codebase.
 *
 * Every entry below is DECLARED here and CONSUMED somewhere real (the consumer
 * is named in each `description`/comment). Adding a setting means adding it
 * here and reading it through PlatformSettings - the form, its validation and
 * its defaults all follow automatically.
 *
 * Keys that exist in the database but not in this registry are not thrown away:
 * the settings page lists them in a separate "custom keys" section so nothing a
 * previous admin stored is silently lost.
 */
class SettingRegistry
{
    /**
     * @return array<int, array{label: string, description: string, icon: string, settings: array<int, array<string, mixed>>}>
     */
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

    /**
     * Flat key => definition map.
     *
     * @return array<string, array<string, mixed>>
     */
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

    /**
     * The `min:`/`max:` bounds a setting already declares, as HTML input
     * attributes.
     *
     * Read from the SAME rules the server validates with, deliberately. The
     * settings form used to hardcode `min="1" max="365"` on every Number field -
     * correct for the two day-count settings that existed, and silently wrong
     * for the first Number setting added with any other range. A browser
     * constraint that contradicts the server rule is worse than none: it blocks
     * a value the server would have accepted, with no error to explain why.
     *
     * @param  array<string, mixed>  $definition
     * @return array{min: ?string, max: ?string}
     */
    public function boundsFor(array $definition): array
    {
        $bounds = ['min' => null, 'max' => null];

        foreach ((array) ($definition['rules'] ?? []) as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            // `max:120` on a string is a LENGTH, not a value - applying it as a
            // number bound would cap a numeric field at its own digit count.
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

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return array_map(fn (array $d) => $d['default'], $this->all());
    }

    /**
     * Validation rules for the grouped form, keyed by the request field name.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->all() as $key => $definition) {
            // Checkboxes are absent from the payload when unticked, so a
            // boolean can never be `required` - it is normalised in the request
            // instead.
            $rules["settings.{$key}"] = $definition['type'] === SettingType::Boolean
                ? ['nullable', 'boolean']
                : $definition['rules'];
        }

        return $rules;
    }

    /**
     * Human labels for validation messages, so an error reads "مدة التجربة"
     * rather than "settings.trial_days".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->all() as $key => $definition) {
            $attributes["settings.{$key}"] = __($definition['label']);
        }

        return $attributes;
    }
}
