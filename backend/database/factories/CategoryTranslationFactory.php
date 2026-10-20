<?php

namespace Database\Factories;

use App\Enums\Locale;
use App\Models\Category;
use App\Models\CategoryTranslation;
use Database\Factories\Concerns\TestingOnly;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CategoryTranslation>
 */
class CategoryTranslationFactory extends Factory
{
    use TestingOnly;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::limit(ucwords(fake()->unique()->words(2, true)), 70, '');

        return [
            'category_id' => Category::factory(),
            'locale' => Locale::En,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
        ];
    }

    public function locale(Locale $locale): static
    {
        return $this->state(fn (array $attributes) => ['locale' => $locale]);
    }
}
