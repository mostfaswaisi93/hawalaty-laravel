<?php

namespace Tests\Unit\WhatsApp;

use App\Services\WhatsApp\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Extends Tests\TestCase (not the bare PHPUnit one) because PhoneNumber reads
 * config('whatsapp.*'); it touches no database.
 */
class PhoneNumberTest extends TestCase
{
    public static function numbers(): array
    {
        return [
            // The number supplied by the operator: country code pasted in front
            // of the trunk-prefixed local number. The stray 0 must collapse.
            'operator paste with trunk zero' => ['+9720595817016', '+972595817016'],
            'bare local with leading zero' => ['0595817016', '+972595817016'],
            'arabic-indic digits' => ['٠٥٩٥٨١٧٠١٦', '+972595817016'],
            'separators and spaces' => ['059-581 7016', '+972595817016'],
            'double-zero international prefix' => ['00972595817016', '+972595817016'],
            'already e164 (idempotent)' => ['+972595817016', '+972595817016'],
            'no plus, has country code' => ['972595817016', '+972595817016'],
            'legacy seeder value' => ['0599000001', '+972599000001'],
            'palestinian code preserved' => ['+970595817016', '+970595817016'],
            'palestinian code with trunk zero' => ['+9700595817016', '+970595817016'],

            'null' => [null, null],
            'empty' => ['', null],
            'whitespace only' => ['   ', null],
            'letters only' => ['abc', null],
            'too short' => ['123', null],
            'too long' => ['+9721234567890123', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_normalises_to_e164(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::toE164($input));
    }

    public function test_normalisation_is_idempotent(): void
    {
        foreach (['+9720595817016', '0595817016', '00972595817016', '972595817016'] as $raw) {
            $once = PhoneNumber::toE164($raw);

            $this->assertSame($once, PhoneNumber::toE164($once));
        }
    }

    public function test_wire_format_strips_the_plus(): void
    {
        $this->assertSame('972595817016', PhoneNumber::toWireFormat('+972595817016'));
    }

    public function test_mask_keeps_only_the_last_four_digits(): void
    {
        $this->assertSame('****7016', PhoneNumber::mask('+972595817016'));
        $this->assertSame('****', PhoneNumber::mask(null));
        $this->assertSame('****', PhoneNumber::mask('12'));
    }

    public function test_default_country_code_is_configurable(): void
    {
        config(['whatsapp.default_country_code' => '970']);

        $this->assertSame('+970595817016', PhoneNumber::toE164('0595817016'));
    }
}
