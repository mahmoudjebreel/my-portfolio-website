<?php

namespace Database\Factories;

use App\Enums\Locale;
use App\Models\ProjectImage;
use App\Models\ProjectImageTranslation;
use Database\Factories\Concerns\TestingOnly;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectImageTranslation>
 */
class ProjectImageTranslationFactory extends Factory
{
    use TestingOnly;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_image_id' => ProjectImage::factory(),
            'locale' => Locale::En,
            'alt_text' => Str::limit(fake()->sentence(6), 190, ''),
        ];
    }

    public function locale(Locale $locale): static
    {
        return $this->state(fn (array $attributes) => ['locale' => $locale]);
    }
}
