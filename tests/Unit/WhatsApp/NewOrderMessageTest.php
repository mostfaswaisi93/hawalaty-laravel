<?php

namespace Tests\Unit\WhatsApp;

use App\Enums\PaymentMethod;
use App\Enums\TransferStatus;
use App\Models\Tenant\Bank;
use App\Models\Tenant\Transfer;
use App\Models\Tenant\TransferLineItem;
use App\Services\WhatsApp\NewOrderMessage;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

/**
 * No RefreshDatabase, and every model here is UNSAVED with its relations set
 * explicitly. The tenant schema (database/migrations/tenant) cannot run against
 * the sqlite test database - several of those migrations use MySQL-only DDL -
 * so nothing in this feature may touch the `tenant` connection in a test.
 */
class NewOrderMessageTest extends TestCase
{
    private function transfer(array $attributes = [], bool $withLineItems = true): Transfer
    {
        $bank = new Bank(['name' => 'بنك فلسطين', 'code' => 'BOP', 'phone' => '+972595817016']);

        $transfer = new Transfer(array_merge([
            'customer' => 'أحمد محمد',
            'status' => TransferStatus::Pending,
            'amount' => '72.00',
            'item_name' => 'شاورما دجاج ×2، عصير برتقال ×1',
            'payment_method' => PaymentMethod::Bank,
        ], $attributes));

        // HasUlids only fires on `creating`, and an unsaved model has a null
        // created_at - both would otherwise blow up the renderer.
        $transfer->id = '01HQZX9K2M4N6P8R0A1B2C3D4E';
        $transfer->created_at = now();

        $transfer->setRelation('bank', $bank);
        $transfer->setRelation('lineItems', $withLineItems ? new Collection([
            new TransferLineItem(['name' => 'شاورما دجاج', 'quantity' => 2, 'price' => '30.00']),
            new TransferLineItem(['name' => 'عصير برتقال', 'quantity' => 1, 'price' => '12.00']),
        ]) : new Collection);

        return $transfer;
    }

    public function test_it_includes_every_core_field(): void
    {
        $body = NewOrderMessage::build($this->transfer());

        $this->assertStringContainsString('طلب جديد', $body);
        $this->assertStringContainsString('#1B2C3D4E', $body);    // last 8 of the ULID, uppercased
        $this->assertStringContainsString('أحمد محمد', $body);
        $this->assertStringContainsString('شاورما دجاج', $body);
        $this->assertStringContainsString('60.00 ₪', $body);       // 2 × 30.00 line total
        $this->assertStringContainsString('12.00 ₪', $body);
        $this->assertStringContainsString('72.00 ₪', $body);       // order total
        $this->assertStringContainsString('بنك فلسطين', $body);
        $this->assertStringContainsString('تحويل بنكي', $body);
    }

    public function test_it_renders_arabic_even_when_the_app_locale_is_english(): void
    {
        // SetLocale only runs on the `web` group and APP_LOCALE is `en`, so an
        // API- or sync-created order renders with the locale left at English.
        // The body must still be Arabic.
        $this->app->setLocale('en');

        $body = NewOrderMessage::build($this->transfer());

        $this->assertStringContainsString('طلب جديد', $body);
        $this->assertStringContainsString('الإجمالي', $body);
    }

    public function test_it_omits_the_transferred_amount_when_absent(): void
    {
        $body = NewOrderMessage::build($this->transfer());

        $this->assertStringNotContainsString('المبلغ المُحوَّل', $body);

        // The regression this really guards: rendering the literal word "null".
        $this->assertStringNotContainsString('null', $body);
    }

    public function test_it_never_includes_the_sender_name_or_phone(): void
    {
        // Deliberately excluded from the notification even when captured on the
        // order: the bank only needs the order itself. They remain on the form,
        // in the database and on the printed receipt.
        $body = NewOrderMessage::build($this->transfer([
            'sender_name' => 'خالد شاهين',
            'sender_phone' => '+972598887777',
        ]));

        $this->assertStringNotContainsString('اسم المُرسِل', $body);
        $this->assertStringNotContainsString('هاتف المُرسِل', $body);
        $this->assertStringNotContainsString('خالد شاهين', $body);
        $this->assertStringNotContainsString('+972598887777', $body);
    }

    public function test_it_includes_the_transferred_amount_when_present(): void
    {
        $body = NewOrderMessage::build($this->transfer(['transferred_amount' => '72.00']));

        $this->assertStringContainsString('المبلغ المُحوَّل', $body);
    }

    public function test_it_wraps_latin_runs_in_bidi_isolates(): void
    {
        $body = NewOrderMessage::build($this->transfer());

        // Without LRI…PDI, WhatsApp reorders the trailing ₪ and any leading +
        // when it lays the message out as one RTL paragraph.
        $this->assertStringContainsString("\u{2066}72.00 ₪\u{2069}", $body);
        $this->assertStringContainsString("\u{2066}#1B2C3D4E\u{2069}", $body);
    }

    public function test_it_falls_back_to_the_item_name_summary_without_line_items(): void
    {
        $body = NewOrderMessage::build($this->transfer(withLineItems: false));

        $this->assertStringContainsString('شاورما دجاج ×2، عصير برتقال ×1', $body);
    }

    public function test_it_formats_money_from_the_decimal_cast_string(): void
    {
        // `amount` is cast decimal:2 and therefore reads back as a STRING;
        // number_format() would fatal on it without the (float) cast.
        $body = NewOrderMessage::build($this->transfer(['amount' => '1234.5']));

        $this->assertStringContainsString('1,234.50 ₪', $body);
    }
}
