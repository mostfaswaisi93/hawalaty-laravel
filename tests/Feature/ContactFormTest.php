<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The landing page's contact form.
 *
 * It is the only unauthenticated write in the whole application, which is why
 * every guard on it - validation, the honeypot, the rate limit - is asserted
 * here rather than assumed.
 */
class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'سامي نبهان',
            'email' => 'sami@example.com',
            'phone' => '+970 59 000 0000',
            'company' => 'بقالة النور',
            'subject' => 'sales',
            'message' => 'أريد معرفة الفرق بين باقة الأعمال وباقة إنتربرايز.',
        ], $overrides);
    }

    public function test_a_visitor_can_send_a_message(): void
    {
        Mail::fake();

        $response = $this->post(route('contact.store'), $this->payload());

        $response->assertRedirect(route('home').'#contact');
        $response->assertSessionHas('success');

        $message = ContactMessage::sole();
        $this->assertSame('sami@example.com', $message->email);
        $this->assertSame('sales', $message->subject);
        $this->assertTrue($message->isUnread());
    }

    /**
     * The row is the record and the mail is a notification about it, so a mail
     * transport that is down must not cost a lead. Nothing here fakes a
     * failure - it asserts the ORDER of concerns: the message exists whether or
     * not anything was ever delivered.
     */
    public function test_the_message_is_stored_even_with_no_support_address_configured(): void
    {
        Mail::fake();
        app(PlatformSettings::class)->put(['support_email' => '']);

        $this->post(route('contact.store'), $this->payload())->assertSessionHas('success');

        $this->assertDatabaseCount('contact_messages', 1);
        Mail::assertNothingSent();
    }

    public function test_it_records_the_plan_a_visitor_was_looking_at(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->payload(['plan' => 'إنتربرايز']));

        $this->assertSame('إنتربرايز', ContactMessage::sole()->plan);
    }

    /**
     * The honeypot is invisible to people and to assistive tech, so anything
     * that fills it is automated. Rejecting rather than silently dropping keeps
     * the behaviour observable - a silent discard is indistinguishable from a
     * bug the first time a real submission goes missing.
     */
    public function test_a_filled_honeypot_is_rejected(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->payload(['website' => 'https://spam.example']))
            ->assertSessionHasErrors('website');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidPayloads(): array
    {
        return [
            'no name' => [['name' => ''], 'name'],
            'no email' => [['email' => ''], 'email'],
            'malformed email' => [['email' => 'not-an-email'], 'email'],
            'unknown subject' => [['subject' => 'anything'], 'subject'],
            'no message' => [['message' => ''], 'message'],
            'message too short' => [['message' => 'قصيرة'], 'message'],
            'letters in the phone' => [['phone' => 'call me maybe'], 'phone'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     *
     * @dataProvider invalidPayloads
     */
    #[DataProvider('invalidPayloads')]
    public function test_it_rejects_an_invalid_submission(array $overrides, string $field): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->payload($overrides))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('contact_messages', 0);
    }

    /**
     * Six an hour is far above what any real enquiry needs and far below what
     * makes a scripted flood worth writing.
     */
    public function test_it_throttles_a_flood_from_one_address(): void
    {
        Mail::fake();

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('contact.store'), $this->payload(['email' => "sender{$i}@example.com"]))
                ->assertSessionHas('success');
        }

        $this->post(route('contact.store'), $this->payload(['email' => 'seventh@example.com']))
            ->assertStatus(429);

        $this->assertDatabaseCount('contact_messages', 6);
    }
}
