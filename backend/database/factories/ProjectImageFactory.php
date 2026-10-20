<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectImage;
use Database\Factories\Concerns\TestingOnly;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectImage>
 */
class ProjectImageFactory extends Factory
{
    use TestingOnly;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'path' => 'projects/'.Str::uuid().'.webp',
            'width' => 1280,
            'height' => 720,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
