<?php

namespace Database\Factories;

use App\Models\SiteSetting;
use Database\Factories\Concerns\TestingOnly;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteSetting>
 */
class SiteSettingFactory extends Factory
{
    use TestingOnly;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'test_'.fake()->unique()->numberBetween(1, 99999999),
            'value' => ['enabled' => true],
        ];
    }
}
