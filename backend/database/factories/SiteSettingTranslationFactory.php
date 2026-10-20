<?php

namespace Database\Factories;

use App\Enums\Locale;
use App\Models\SiteSetting;
use App\Models\SiteSettingTranslation;
use Database\Factories\Concerns\TestingOnly;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteSettingTranslation>
 */
class SiteSettingTranslationFactory extends Factory
{
    use TestingOnly;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_setting_id' => SiteSetting::factory(),
            'locale' => Locale::En,
            'value' => fake()->sentence(),
        ];
    }

    public function locale(Locale $locale): static
    {
        return $this->state(fn (array $attributes) => ['locale' => $locale]);
    }
}
