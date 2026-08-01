<?php

namespace App\Settings;

enum SettingType: string
{
    case Text = 'text';
    case Email = 'email';
    case Number = 'number';
    case Boolean = 'boolean';
    case Textarea = 'textarea';

    public function cast(?string $raw): mixed
    {
        return match ($this) {
            self::Number => $raw === null || $raw === '' ? null : (int) $raw,
            self::Boolean => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            default => $raw,
        };
    }

    public function serialize(mixed $value): ?string
    {
        return match ($this) {
            self::Boolean => $value ? '1' : '0',
            default => $value === null || $value === '' ? null : (string) $value,
        };
    }
}
