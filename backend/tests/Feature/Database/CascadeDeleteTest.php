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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CascadeDeleteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{project: Project, category: Category, technology: Technology, image: ProjectImage}
     */
    private function graph(): array
    {
        $project = Project::factory()->create();
        ProjectTranslation::factory()->for($project)->create();
        ProjectTranslation::factory()->for($project)->locale(Locale::Ar)->create();

        $category = Category::factory()->create();
        CategoryTranslation::factory()->for($category)->create();
        CategoryTranslation::factory()->for($category)->locale(Locale::Ar)->create();

        $technology = Technology::factory()->create();
        TechnologyTranslation::factory()->for($technology)->create();
        TechnologyTranslation::factory()->for($technology)->locale(Locale::Ar)->create();

        $project->categories()->attach($category);
        $project->technologies()->attach($technology);

        $image = ProjectImage::factory()->for($project)->create();
        ProjectImageTranslation::factory()->for($image)->create();
        ProjectImageTranslation::factory()->for($image)->locale(Locale::Ar)->create();

        return compact('project', 'category', 'technology', 'image');
    }

    public function test_deleting_a_project_cascades_to_its_children_but_keeps_categories_and_technologies(): void
    {
        ['project' => $project, 'category' => $category, 'technology' => $technology, 'image' => $image] = $this->graph();

        // Sanity: everything is wired up before the delete.
        $this->assertDatabaseCount('project_translations', 2);
        $this->assertDatabaseHas('project_category', ['project_id' => $project->id, 'category_id' => $category->id]);
        $this->assertDatabaseHas('project_technology', ['project_id' => $project->id, 'technology_id' => $technology->id]);
        $this->assertDatabaseCount('project_image_translations', 2);

        $project->delete();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('project_translations', ['project_id' => $project->id]);
        $this->assertDatabaseMissing('project_category', ['project_id' => $project->id]);
        $this->assertDatabaseMissing('project_technology', ['project_id' => $project->id]);
        $this->assertDatabaseMissing('project_images', ['project_id' => $project->id]);
        $this->assertDatabaseMissing('project_image_translations', ['project_image_id' => $image->id]);

        $this->assertModelExists($category);
        $this->assertModelExists($technology);
        $this->assertDatabaseCount('category_translations', 2);
        $this->assertDatabaseCount('technology_translations', 2);
    }

    public function test_deleting_a_category_removes_its_translations_and_pivot_rows_but_keeps_projects(): void
    {
        ['project' => $project, 'category' => $category, 'technology' => $technology] = $this->graph();

        $category->delete();

        $this->assertModelMissing($category);
        $this->assertDatabaseMissing('category_translations', ['category_id' => $category->id]);
        $this->assertDatabaseMissing('project_category', ['category_id' => $category->id]);

        $this->assertModelExists($project);
        $this->assertDatabaseCount('project_translations', 2);
        $this->assertDatabaseHas('project_technology', ['project_id' => $project->id, 'technology_id' => $technology->id]);
    }

    public function test_deleting_a_technology_removes_its_translations_and_pivot_rows_but_keeps_projects(): void
    {
        ['project' => $project, 'category' => $category, 'technology' => $technology] = $this->graph();

        $technology->delete();

        $this->assertModelMissing($technology);
        $this->assertDatabaseMissing('technology_translations', ['technology_id' => $technology->id]);
        $this->assertDatabaseMissing('project_technology', ['technology_id' => $technology->id]);

        $this->assertModelExists($project);
        $this->assertDatabaseCount('project_translations', 2);
        $this->assertDatabaseHas('project_category', ['project_id' => $project->id, 'category_id' => $category->id]);
    }

    public function test_deleting_a_project_image_removes_its_translations_only(): void
    {
        ['project' => $project, 'image' => $image] = $this->graph();

        $image->delete();

        $this->assertModelMissing($image);
        $this->assertDatabaseMissing('project_image_translations', ['project_image_id' => $image->id]);
        $this->assertModelExists($project);
        $this->assertDatabaseCount('project_translations', 2);
    }

    public function test_deleting_a_site_setting_removes_its_translations(): void
    {
        $setting = SiteSetting::factory()->create();
        SiteSettingTranslation::factory()->for($setting)->create();
        SiteSettingTranslation::factory()->for($setting)->locale(Locale::Ar)->create();
        $other = SiteSettingTranslation::factory()->create();

        $setting->delete();

        $this->assertModelMissing($setting);
        $this->assertDatabaseMissing('site_setting_translations', ['site_setting_id' => $setting->id]);
        $this->assertModelExists($other);
    }
}
