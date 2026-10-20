<?php

namespace Tests\Feature\Database;

use App\Enums\Locale;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Project;
use App\Models\ProjectImage;
use App\Models\ProjectImageTranslation;
use App\Models\ProjectTranslation;
use App\Models\SiteSetting;
use App\Models\SiteSettingTranslation;
use App\Models\Technology;
use App\Models\TechnologyTranslation;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UniqueConstraintTest extends TestCase
{
    use RefreshDatabase;

    private function assertUniqueViolation(Closure $callback): void
    {
        try {
            $callback();
        } catch (UniqueConstraintViolationException $e) {
            $this->assertStringContainsString('Duplicate entry', $e->getMessage());

            return;
        }

        $this->fail('Expected a UniqueConstraintViolationException, but the write succeeded.');
    }

    public function test_project_translation_project_and_locale_is_unique(): void
    {
        $project = Project::factory()->create();
        ProjectTranslation::factory()->for($project)->create();

        $this->assertUniqueViolation(fn () => ProjectTranslation::factory()->for($project)->create());
    }

    public function test_project_translation_slug_is_unique_within_a_locale(): void
    {
        ProjectTranslation::factory()->create(['slug' => 'my-project']);

        $this->assertUniqueViolation(fn () => ProjectTranslation::factory()->create(['slug' => 'my-project']));
    }

    public function test_the_same_project_slug_is_allowed_in_different_locales(): void
    {
        $en = ProjectTranslation::factory()->create(['slug' => 'my-project']);
        $ar = ProjectTranslation::factory()->locale(Locale::Ar)->create(['slug' => 'my-project']);

        $this->assertNotSame($en->project_id, $ar->project_id);
        $this->assertSame(2, ProjectTranslation::query()->where('slug', 'my-project')->count());
    }

    public function test_category_translation_name_is_unique_within_a_locale(): void
    {
        CategoryTranslation::factory()->create(['name' => 'Web Apps']);

        $this->assertUniqueViolation(fn () => CategoryTranslation::factory()->create(['name' => 'Web Apps']));
    }

    public function test_category_translation_slug_is_unique_within_a_locale(): void
    {
        CategoryTranslation::factory()->create(['slug' => 'web-apps']);

        $this->assertUniqueViolation(fn () => CategoryTranslation::factory()->create(['slug' => 'web-apps']));
    }

    public function test_category_translation_category_and_locale_is_unique(): void
    {
        $category = Category::factory()->create();
        CategoryTranslation::factory()->for($category)->create();

        $this->assertUniqueViolation(fn () => CategoryTranslation::factory()->for($category)->create());
    }

    public function test_technology_name_is_unique(): void
    {
        Technology::factory()->create(['name' => 'Laravel']);

        $this->assertUniqueViolation(fn () => Technology::factory()->create(['name' => 'Laravel']));
    }

    public function test_technology_slug_is_unique(): void
    {
        Technology::factory()->create(['slug' => 'laravel']);

        $this->assertUniqueViolation(fn () => Technology::factory()->create(['slug' => 'laravel']));
    }

    public function test_technology_translation_technology_and_locale_is_unique(): void
    {
        $technology = Technology::factory()->create();
        TechnologyTranslation::factory()->for($technology)->create();

        $this->assertUniqueViolation(fn () => TechnologyTranslation::factory()->for($technology)->create());
    }

    public function test_project_category_pivot_pair_is_unique(): void
    {
        $project = Project::factory()->create();
        $category = Category::factory()->create();
        $project->categories()->attach($category);

        $this->assertUniqueViolation(fn () => DB::table('project_category')->insert([
            'project_id' => $project->id,
            'category_id' => $category->id,
        ]));
    }

    public function test_project_technology_pivot_pair_is_unique(): void
    {
        $project = Project::factory()->create();
        $technology = Technology::factory()->create();
        $project->technologies()->attach($technology);

        $this->assertUniqueViolation(fn () => DB::table('project_technology')->insert([
            'project_id' => $project->id,
            'technology_id' => $technology->id,
        ]));
    }

    public function test_site_setting_key_is_unique(): void
    {
        SiteSetting::factory()->create(['key' => 'social_links']);

        $this->assertUniqueViolation(fn () => SiteSetting::factory()->create(['key' => 'social_links']));
    }

    public function test_site_setting_translation_setting_and_locale_is_unique(): void
    {
        $setting = SiteSetting::factory()->create();
        SiteSettingTranslation::factory()->for($setting)->create();

        $this->assertUniqueViolation(fn () => SiteSettingTranslation::factory()->for($setting)->create());
    }

    public function test_project_image_translation_image_and_locale_is_unique(): void
    {
        $image = ProjectImage::factory()->create();
        ProjectImageTranslation::factory()->for($image)->create();

        $this->assertUniqueViolation(fn () => ProjectImageTranslation::factory()->for($image)->create());
    }
}
