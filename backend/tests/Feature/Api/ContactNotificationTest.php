<?php

namespace Tests\Feature\Api;

use App\Mail\NewContactMessageMail;
use App\Models\ContactMessage;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Email notification for new contact messages, and the panel's "Back to the website" button.
 * Test database only (TestCase guard + RefreshDatabase); mail is faked, nothing is sent.
 */
class ContactNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer();
    }

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Sara Ahmad',
            'email' => 'sara@example.com',
            'subject' => 'A new website',
            'message' => 'Hello, I would like to talk about a project.',
            'locale' => 'ar',
        ];
    }

    private function setNotificationEmail(?string $email): void
    {
        SiteSetting::create(['key' => 'notification_email', 'value' => $email]);
    }

    public function test_a_new_message_is_emailed_to_the_notification_address(): void
    {
        Mail::fake();
        $this->setNotificationEmail('owner@example.com');

        $this->postJson('/api/contact-messages', $this->payload())->assertCreated();

        $message = ContactMessage::query()->sole();
        Mail::assertSent(NewContactMessageMail::class, function (NewContactMessageMail $mail) use ($message): bool {
            return $mail->hasTo('owner@example.com')
                && $mail->hasReplyTo('sara@example.com')
                && $mail->contactMessage->is($message);
        });
        $this->assertNotNull($message->notified_at);
    }

    public function test_nothing_is_sent_without_a_valid_notification_address(): void
    {
        Mail::fake();

        $this->postJson('/api/contact-messages', $this->payload())->assertCreated();
        $this->setNotificationEmail('not an email');
        $this->postJson('/api/contact-messages', $this->payload())->assertCreated();

        Mail::assertNothingSent();
        $this->assertSame(2, ContactMessage::query()->whereNull('notified_at')->count());
    }

    public function test_a_mail_failure_never_loses_the_message(): void
    {
        Exceptions::fake();
        $this->setNotificationEmail('owner@example.com');
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Mail server unavailable'));

        $this->postJson('/api/contact-messages', $this->payload())->assertCreated();

        $message = ContactMessage::query()->sole();
        $this->assertNull($message->notified_at);
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_the_email_shows_the_visitor_text_escaped_and_links_to_the_panel(): void
    {
        $message = ContactMessage::factory()->create([
            'name' => '<b>Sara</b>',
            'subject' => 'Hi <img src=x onerror=alert(1)>',
            'message' => "<script>alert('x')</script>\nSecond line",
        ]);

        $html = (new NewContactMessageMail($message))->locale('ar')->render();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString("/admin/contact-messages/{$message->id}", $html);
        $this->assertStringContainsString('وصلت رسالة جديدة من الموقع', $html);
    }

    public function test_the_panel_has_a_back_to_the_website_button_in_its_language(): void
    {
        config(['app.frontend_url' => 'https://site.example/']);
        $this->actingAs(User::factory()->admin()->create(), 'web');

        $this->get('/admin')->assertOk()
            ->assertSee('href="https://site.example/en"', false)
            ->assertSee('Back to the website');

        $this->withSession(['admin_locale' => 'ar'])->get('/admin')->assertOk()
            ->assertSee('href="https://site.example/ar"', false)
            ->assertSee('الرجوع إلى الواجهة');
    }

    public function test_the_message_page_shows_whether_the_owner_was_notified(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'web');
        $pending = ContactMessage::factory()->create(['notified_at' => null]);

        $this->get("/admin/contact-messages/{$pending->id}")->assertOk()
            ->assertSee(__('admin.messages.fields.notified_at'))
            ->assertSee(__('admin.messages.not_notified'));
    }
}
