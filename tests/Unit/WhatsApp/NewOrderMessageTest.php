<?php

namespace Tests\Unit\WhatsApp;

use App\Enums\AccountKind;
use App\Enums\PaymentMethod;
use App\Enums\TransferStatus;
use App\Models\Tenant\Account;
use App\Models\Tenant\BankName;
use App\Models\Tenant\Customer;
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
        // `kind` decides which institution relation label() reads, so it is as
        // load-bearing here as the relation itself. Both are set explicitly: a
        // lazy load would reach for the tenant connection.
        $account = new Account([
            'kind' => AccountKind::Bank,
            'holder_name' => 'أحمد',
            'whatsapp_phone' => '+972595817016',
        ]);
        $account->setRelation('bankName', new BankName(['name' => 'بنك فلسطين']));

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

        $transfer->setRelation('account', $account);
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

    public function test_it_includes_the_customer_phone_when_the_profile_is_loaded(): void
    {
        $transfer = $this->transfer();
        $transfer->setRelation('customerProfile', new Customer([
            'name' => 'أحمد محمد',
            'phone' => '+972595817016',
        ]));

        $body = NewOrderMessage::build($transfer);

        // Wrapped in bidi isolates like every other Latin/numeric run, so the
        // leading + does not jump to the wrong end of the RTL paragraph.
        $this->assertStringContainsString("الهاتف: \u{2066}+972595817016\u{2069}", $body);
    }

    public function test_it_omits_the_phone_when_the_order_has_no_customer_profile(): void
    {
        // No phone on the order means customer_id - and customerProfile - is
        // null; the message must simply drop the line, never render "الهاتف: ".
        $body = NewOrderMessage::build($this->transfer());

        $this->assertStringNotContainsString('الهاتف', $body);
    }

    public function test_it_appends_the_confirmation_link_when_one_is_passed(): void
    {
        $url = 'https://hawalaty.test/order/confirm/ABC123/tok_0123456789';

        $body = NewOrderMessage::build($this->transfer(), $url);

        $this->assertStringContainsString('لتأكيد استلام الحوالة', $body);
        // Isolated like every other Latin run so the URL keeps its direction.
        $this->assertStringContainsString("\u{2066}{$url}\u{2069}", $body);
    }

    public function test_it_omits_the_confirmation_link_when_none_is_passed(): void
    {
        $body = NewOrderMessage::build($this->transfer());

        $this->assertStringNotContainsString('لتأكيد استلام الحوالة', $body);
        $this->assertStringNotContainsString('http', $body);
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

    // --- parameters(): the single-line values for the multi-variable template ---

    public function test_parameters_returns_exactly_the_declared_number_of_values(): void
    {
        $params = NewOrderMessage::parameters($this->transfer());

        $this->assertCount(NewOrderMessage::PARAM_COUNT, $params);
    }

    public function test_no_parameter_ever_contains_a_newline(): void
    {
        // The whole reason parameters() exists: Meta rejects a body parameter
        // that contains a newline (error 132018). A multi-item order with a
        // confirmation link is the case that used to break.
        $transfer = $this->transfer();
        $transfer->setRelation('customerProfile', new Customer(['name' => 'محمود', 'phone' => '+970599302930']));

        $params = NewOrderMessage::parameters($transfer, "https://app.test/order/confirm/L/tok");

        foreach ($params as $value) {
            $this->assertStringNotContainsString("\n", $value);
            $this->assertStringNotContainsString("\t", $value);
            $this->assertNotSame('', $value); // Meta also rejects an empty parameter
        }
    }

    public function test_parameters_flatten_multiple_line_items_to_one_line(): void
    {
        $params = NewOrderMessage::parameters($this->transfer());

        // Both items must appear, joined into the single items slot ({{5}}).
        $this->assertStringContainsString('شاورما دجاج', $params[4]);
        $this->assertStringContainsString('عصير برتقال', $params[4]);
        $this->assertStringNotContainsString("\n", $params[4]);
    }

    public function test_parameters_include_the_confirmation_url_when_present(): void
    {
        $url = 'https://app.test/order/confirm/LIC/tok_123';

        $params = NewOrderMessage::parameters($this->transfer(), $url);

        $this->assertStringContainsString($url, $params[7]);
    }

    public function test_parameters_use_a_dash_for_absent_optionals(): void
    {
        // A bare bank order: no customer profile (phone), no bank relation, no
        // link. Those slots must be a dash, never an empty string.
        $transfer = new Transfer([
            'customer' => 'أحمد',
            'status' => TransferStatus::Pending,
            'amount' => '10.00',
            'item_name' => 'صنف',
            'payment_method' => PaymentMethod::Bank,
        ]);
        $transfer->id = '01HQZX9K2M4N6P8R0A1B2C3D4E';
        $transfer->created_at = now();

        $params = NewOrderMessage::parameters($transfer);

        $this->assertSame('—', $params[3]); // phone
        $this->assertSame('—', $params[6]); // bank
        $this->assertSame('—', $params[7]); // link
    }

    public function test_test_parameters_match_the_template_arity(): void
    {
        $this->assertCount(NewOrderMessage::PARAM_COUNT, NewOrderMessage::testParameters('مرحبا'));
        $this->assertContains('مرحبا', NewOrderMessage::testParameters('مرحبا'));
    }

    public function test_test_parameters_collapse_to_one_for_a_single_variable_template(): void
    {
        $params = NewOrderMessage::testParameters('مرحبا', 1);

        $this->assertCount(1, $params);
        $this->assertSame('مرحبا', $params[0]);
    }

    // --- forVariables()/singleLine(): the legacy single-parameter template ---

    public function test_for_variables_returns_one_flattened_line_when_the_template_has_one(): void
    {
        $params = NewOrderMessage::forVariables($this->transfer(), 'https://app.test/c/tok', 1);

        $this->assertCount(1, $params);
        // Everything on the one line, and no newline (that is the 132018 trap).
        $this->assertStringNotContainsString("\n", $params[0]);
        $this->assertStringContainsString('أحمد محمد', $params[0]);
        $this->assertStringContainsString('شاورما دجاج', $params[0]);
        $this->assertStringContainsString('https://app.test/c/tok', $params[0]);
    }

    public function test_for_variables_returns_the_full_set_otherwise(): void
    {
        $params = NewOrderMessage::forVariables($this->transfer(), null, NewOrderMessage::PARAM_COUNT);

        $this->assertCount(NewOrderMessage::PARAM_COUNT, $params);
    }

    public function test_single_line_omits_absent_optionals_rather_than_showing_a_dash(): void
    {
        // A bare order with no phone/bank/link must not read "الهاتف: —".
        $transfer = new Transfer([
            'customer' => 'أحمد',
            'status' => TransferStatus::Pending,
            'amount' => '10.00',
            'item_name' => 'صنف',
            'payment_method' => PaymentMethod::Bank,
        ]);
        $transfer->id = '01HQZX9K2M4N6P8R0A1B2C3D4E';
        $transfer->created_at = now();

        $line = NewOrderMessage::singleLine($transfer);

        // The absent fields are dropped whole - no dangling "الهاتف: " / "البنك: "
        // label. (The em dash itself is the field SEPARATOR, so it is present.)
        $this->assertStringNotContainsString('الهاتف', $line);
        $this->assertStringNotContainsString('البنك', $line);
        $this->assertStringNotContainsString('للتأكيد', $line);
    }
}
