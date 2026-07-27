<?php

namespace App\Settings;

/**
 * How a setting is edited on the form and how its stored string is cast back
 * on read. Everything lives in `settings.value` as TEXT, so the type is what
 * turns "14" into an int and "1" into true.
 */
enum SettingType: string
{
    case Text = 'text';
    case Email = 'email';
    case Number = 'number';
    case Boolean = 'boolean';
    case Textarea = 'textarea';

    /** Casts the stored string back to a usable PHP value. */
    public function cast(?string $raw): mixed
    {
        return match ($this) {
            self::Number => $raw === null || $raw === '' ? null : (int) $raw,
            self::Boolean => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            default => $raw,
        };
    }

    /** Serialises a submitted value for storage. */
    public function serialize(mixed $value): ?string
    {
        return match ($this) {
            self::Boolean => $value ? '1' : '0',
            default => $value === null || $value === '' ? null : (string) $value,
        };
    }
}
