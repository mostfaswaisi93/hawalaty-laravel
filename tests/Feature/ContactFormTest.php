<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_a_filled_honeypot_is_rejected(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->payload(['website' => 'https://spam.example']))
            ->assertSessionHasErrors('website');

        $this->assertDatabaseCount('contact_messages', 0);
    }

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

    #[DataProvider('invalidPayloads')]
    public function test_it_rejects_an_invalid_submission(array $overrides, string $field): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->payload($overrides))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('contact_messages', 0);
    }

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
