<?php

namespace Tests\Feature\Api;

use App\Enums\Locale;
use App\Enums\ProjectStatus;
use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoriesApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, array{name: string, slug: string}>  $translations
     */
    private function category(int $order, array $translations): Category
    {
        $category = Category::create(['sort_order' => $order]);
        foreach ($translations as $locale => $translation) {
            $category->translations()->create(['locale' => $locale] + $translation);
        }

        return $category;
    }

    /**
     * @param  list<Locale>  $locales
     */
    private function project(ProjectStatus $status, array $locales): Project
    {
        $project = Project::factory()->create(['status' => $status]);
        foreach ($locales as $locale) {
            ProjectTranslation::factory()->for($project)->locale($locale)->create();
        }

        return $project;
    }

    public function test_categories_are_listed_per_locale_in_display_order(): void
    {
        $this->category(2, [
            'en' => ['name' => 'Landing Pages', 'slug' => 'landing-pages'],
            'ar' => ['name' => 'صفحات الهبوط', 'slug' => 'landing-pages'],
        ]);
        $this->category(1, [
            'en' => ['name' => 'Web Applications', 'slug' => 'web-applications'],
            'ar' => ['name' => 'تطبيقات الويب', 'slug' => 'web-applications'],
        ]);

        $this->getJson('/api/categories?locale=en')
            ->assertOk()
            ->assertExactJsonStructure(['data' => ['*' => ['slug', 'name', 'projects_count']]])
            ->assertJsonPath('data.*.name', ['Web Applications', 'Landing Pages'])
            ->assertDontSee('تطبيقات الويب');

        $this->getJson('/api/categories?locale=ar')
            ->assertJsonPath('data.*.name', ['تطبيقات الويب', 'صفحات الهبوط'])
            ->assertDontSee('Web Applications');
    }

    public function test_category_without_a_translation_in_the_locale_is_not_listed(): void
    {
        $this->category(1, ['en' => ['name' => 'English Only', 'slug' => 'english-only']]);

        $this->getJson('/api/categories?locale=en')->assertJsonCount(1, 'data');
        $this->getJson('/api/categories?locale=ar')->assertExactJson(['data' => []]);
    }

    public function test_projects_count_includes_only_published_projects_translated_in_the_locale(): void
    {
        $category = $this->category(1, [
            'en' => ['name' => 'Web Applications', 'slug' => 'web-applications'],
            'ar' => ['name' => 'تطبيقات الويب', 'slug' => 'web-applications'],
        ]);

        $category->projects()->attach([
            $this->project(ProjectStatus::Published, [Locale::En, Locale::Ar])->id, // counts in en + ar
            $this->project(ProjectStatus::Published, [Locale::En])->id,             // counts in en only
            $this->project(ProjectStatus::Draft, [Locale::En, Locale::Ar])->id,     // never counts
        ]);

        $this->getJson('/api/categories?locale=en')->assertJsonPath('data.0.projects_count', 2);
        $this->getJson('/api/categories?locale=ar')->assertJsonPath('data.0.projects_count', 1);
    }

    public function test_internal_fields_are_not_exposed(): void
    {
        $this->category(5, ['en' => ['name' => 'Tools & Utilities', 'slug' => 'tools-utilities']]);

        $item = $this->getJson('/api/categories')->json('data.0');

        foreach (['id', 'sort_order', 'translations', 'created_at', 'updated_at'] as $field) {
            $this->assertArrayNotHasKey($field, $item);
        }
    }
}
