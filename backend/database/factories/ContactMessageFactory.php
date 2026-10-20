<?php

namespace Database\Factories;

use App\Enums\Locale;
use App\Enums\MessageStatus;
use App\Models\ContactMessage;
use Database\Factories\Concerns\TestingOnly;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    use TestingOnly;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::limit(fake()->name(), 100, ''),
            'email' => fake()->safeEmail(),
            'subject' => Str::limit(fake()->sentence(5), 150, ''),
            'message' => fake()->paragraph(),
            'locale' => fake()->randomElement(Locale::cases()),
            'status' => MessageStatus::Unread,
            'read_at' => null,
            'notified_at' => null,
        ];
    }
}
