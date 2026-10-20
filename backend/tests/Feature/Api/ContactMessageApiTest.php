<?php

namespace Tests\Feature\Api;

use App\Enums\Locale;
use App\Enums\MessageStatus;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Models\ContactMessage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The public contact form endpoint. Test database only (TestCase guard + RefreshDatabase).
 */
class ContactMessageApiTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_a_message_is_stored_as_unread_and_reaches_the_inbox(): void
    {
        $this->postJson('/api/contact-messages', $this->payload(['name' => '  Sara Ahmad  ']))
            ->assertCreated()
            ->assertExactJson(['data' => ['received' => true]])
            ->assertHeaderMissing('Set-Cookie');

        $message = ContactMessage::query()->sole();
        $this->assertSame('Sara Ahmad', $message->name);
        $this->assertSame('sara@example.com', $message->email);
        $this->assertSame(Locale::Ar, $message->locale);
        $this->assertSame(MessageStatus::Unread, $message->status);
        $this->assertNull($message->read_at);

        $this->actingAs(User::factory()->admin()->create(), 'web');
        Filament::setCurrentPanel('admin');
        Livewire::test(ListContactMessages::class)->assertCanSeeTableRecords([$message]);
    }

    public function test_invalid_messages_are_refused_with_field_errors(): void
    {
        $this->postJson('/api/contact-messages', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'subject', 'message', 'locale']);

        $this->postJson('/api/contact-messages', $this->payload([
            'name' => 'S',
            'email' => 'not an email',
            'subject' => 'Hi',
            'message' => 'Too short',
            'locale' => 'fr',
        ]))->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'subject', 'message', 'locale']);

        $this->postJson('/api/contact-messages', $this->payload(['message' => str_repeat('a', 5001), 'name' => ['array']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message', 'name']);

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_only_allowed_fields_are_stored(): void
    {
        $this->postJson('/api/contact-messages', $this->payload(['status' => 'read', 'read_at' => '2026-01-01', 'id' => 99]))
            ->assertCreated();

        $message = ContactMessage::query()->sole();
        $this->assertNotSame(99, $message->id);
        $this->assertSame(MessageStatus::Unread, $message->status);
        $this->assertNull($message->read_at);
    }

    public function test_extra_fields_filled_by_browser_autofill_never_drop_a_message(): void
    {
        // A hidden trap field used to be filled by autofill / password managers, and real
        // messages were silently discarded. Unknown fields are now ignored, never fatal.
        $this->postJson('/api/contact-messages', $this->payload(['website' => 'https://example.com', 'hp_check' => 'x']))
            ->assertCreated();

        $this->assertSame(1, ContactMessage::query()->count());
    }

    public function test_sending_is_rate_limited_per_visitor(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/contact-messages', $this->payload())->assertCreated();
        }

        $this->postJson('/api/contact-messages', $this->payload())->assertStatus(429);
        $this->assertSame(5, ContactMessage::query()->count());
    }

    public function test_the_contact_route_accepts_only_post(): void
    {
        $this->getJson('/api/contact-messages')->assertStatus(405);
        $this->putJson('/api/contact-messages')->assertStatus(405);
        $this->deleteJson('/api/contact-messages')->assertStatus(405);
    }
}
