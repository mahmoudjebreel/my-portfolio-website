<?php

namespace Database\Factories;

use App\Enums\Locale;
use App\Models\Technology;
use App\Models\TechnologyTranslation;
use Database\Factories\Concerns\TestingOnly;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TechnologyTranslation>
 */
class TechnologyTranslationFactory extends Factory
{
    use TestingOnly;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'technology_id' => Technology::factory(),
            'locale' => Locale::En,
            'display_name' => null,
            'description' => Str::limit(fake()->sentence(), 290, ''),
        ];
    }

    public function locale(Locale $locale): static
    {
        return $this->state(fn (array $attributes) => ['locale' => $locale]);
    }
}
