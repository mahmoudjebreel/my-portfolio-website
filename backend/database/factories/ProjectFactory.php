<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Database\Factories\Concerns\TestingOnly;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    use TestingOnly;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cover_image_path' => null,
            'github_url' => null,
            'live_url' => null,
            'status' => ProjectStatus::Draft,
            'is_featured' => false,
            'sort_order' => fake()->numberBetween(0, 100),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => ['is_featured' => true]);
    }
}
