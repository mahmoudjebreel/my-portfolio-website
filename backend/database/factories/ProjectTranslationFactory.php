<?php

namespace Database\Factories;

use App\Enums\Locale;
use App\Models\Project;
use App\Models\ProjectTranslation;
use Database\Factories\Concerns\TestingOnly;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectTranslation>
 */
class ProjectTranslationFactory extends Factory
{
    use TestingOnly;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::limit(rtrim(fake()->sentence(4), '.'), 120, '');

        return [
            'project_id' => Project::factory(),
            'locale' => Locale::En,
            'title' => $title,
            'slug' => rtrim(Str::limit(Str::slug($title), 140, ''), '-').'-'.fake()->unique()->numberBetween(1, 999999),
            'short_description' => Str::limit(fake()->sentence(12), 290, ''),
            'description' => fake()->paragraphs(2, true),
            'problem' => null,
            'solution' => null,
            'cover_image_alt' => null,
        ];
    }

    public function locale(Locale $locale): static
    {
        return $this->state(fn (array $attributes) => ['locale' => $locale]);
    }
}
