<?php

namespace Database\Factories;

use App\Enums\TechnologyGroup;
use App\Models\Technology;
use Database\Factories\Concerns\TestingOnly;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Technology>
 */
class TechnologyFactory extends Factory
{
    use TestingOnly;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::limit(ucfirst(fake()->unique()->word()), 40, '').' '.fake()->unique()->numberBetween(1, 999999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'group' => fake()->randomElement(TechnologyGroup::cases()),
            'icon' => null,
            'is_visible' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
