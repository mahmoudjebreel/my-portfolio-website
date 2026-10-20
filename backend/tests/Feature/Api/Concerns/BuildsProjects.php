<?php

namespace Tests\Feature\Api\Concerns;

use App\Enums\ProjectStatus;
use App\Enums\TechnologyGroup;
use App\Models\Category;
use App\Models\Project;
use App\Models\Technology;

/**
 * Small, explicit builders for project API tests (test database only).
 */
trait BuildsProjects
{
    /**
     * @param  array<string, array<string, mixed>>  $translations  locale => translation attributes
     * @param  array<string, mixed>  $attributes
     */
    protected function makeProject(array $translations, array $attributes = []): Project
    {
        $project = Project::factory()->create($attributes + [
            'status' => ProjectStatus::Published,
            'published_at' => now(),
            'is_featured' => false,
            'sort_order' => 0,
        ]);

        foreach ($translations as $locale => $translation) {
            $project->translations()->create(['locale' => $locale] + $translation + [
                'title' => "Title {$locale} {$project->id}",
                'short_description' => "Short {$locale} {$project->id}",
                'description' => "Description {$locale} {$project->id}",
            ]);
        }

        return $project;
    }

    /**
     * Published project translated in English only, with a predictable slug.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function englishProject(string $slug, array $attributes = []): Project
    {
        return $this->makeProject(['en' => ['slug' => $slug, 'title' => ucfirst(str_replace('-', ' ', $slug))]], $attributes);
    }

    /**
     * @param  array<string, array{name: string, slug: string}>  $translations
     */
    protected function makeCategory(array $translations, int $order = 0): Category
    {
        $category = Category::create(['sort_order' => $order]);
        foreach ($translations as $locale => $translation) {
            $category->translations()->create(['locale' => $locale] + $translation);
        }

        return $category;
    }

    protected function makeTechnology(string $name, bool $visible = true, int $order = 0): Technology
    {
        return Technology::create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'group' => TechnologyGroup::Backend,
            'icon' => null,
            'is_visible' => $visible,
            'sort_order' => $order,
        ]);
    }
}
