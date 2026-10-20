<?php

namespace Tests\Feature\Filament;

use App\Enums\ProjectStatus;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Technologies\Pages\CreateTechnology;
use App\Filament\Resources\Technologies\Pages\EditTechnology;
use App\Filament\Resources\Technologies\Pages\ListTechnologies;
use App\Filament\Widgets\AdminStatsOverview;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Project;
use App\Models\Technology;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Api\Concerns\BuildsProjects;
use Tests\TestCase;

/**
 * Filament resources (F3): projects, categories, technologies and the dashboard, through the
 * same rules and actions as the admin API. Test database only (TestCase guard + RefreshDatabase).
 */
class FilamentResourcesTest extends TestCase
{
    use BuildsProjects;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        $this->actingAs(User::factory()->admin()->create(), 'web');
        Filament::setCurrentPanel('admin');
    }

    /**
     * @return array<string, mixed>
     */
    private function content(string $slug, string $title): array
    {
        return ['title' => $title, 'slug' => $slug, 'short_description' => 'Short', 'description' => 'Long'];
    }

    public function test_every_page_opens_for_an_administrator_and_not_for_others(): void
    {
        $project = $this->englishProject('page-check');
        $category = $this->makeCategory(['en' => ['name' => 'Web', 'slug' => 'web']]);
        $technology = $this->makeTechnology('Laravel');
        $pages = [
            '/admin', '/admin/projects', '/admin/projects/create', "/admin/projects/{$project->id}/edit",
            '/admin/categories', '/admin/categories/create', "/admin/categories/{$category->id}/edit",
            '/admin/technologies', '/admin/technologies/create', "/admin/technologies/{$technology->id}/edit",
        ];

        foreach ($pages as $page) {
            $this->get($page)->assertOk();
        }

        $this->actingAs(User::factory()->create(), 'web');
        foreach ($pages as $page) {
            $this->get($page)->assertForbidden();
        }
    }

    public function test_the_project_list_searches_titles_and_offers_delete(): void
    {
        $shop = $this->makeProject(['en' => ['slug' => 'shop', 'title' => 'Online Shop'], 'ar' => ['slug' => 'matjar', 'title' => 'متجر']]);
        $blog = $this->englishProject('blog', ['status' => ProjectStatus::Draft, 'published_at' => null]);

        Livewire::test(ListProjects::class)
            ->assertCanSeeTableRecords([$shop, $blog])
            ->searchTable('متجر')
            ->assertCanSeeTableRecords([$shop])
            ->assertCanNotSeeTableRecords([$blog])
            ->assertTableActionExists('delete');

        Livewire::test(ListProjects::class)
            ->filterTable('status', 'draft')
            ->assertCanSeeTableRecords([$blog])
            ->assertCanNotSeeTableRecords([$shop]);
    }

    public function test_a_draft_is_created_with_one_language_through_the_shared_rules(): void
    {
        $category = $this->makeCategory(['en' => ['name' => 'Web', 'slug' => 'web']]);

        Livewire::test(CreateProject::class)
            ->fillForm([
                'status' => 'draft',
                'sort_order' => 2,
                'category_ids' => [$category->id],
                'translations' => ['en' => $this->content('portfolio', 'Portfolio')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::query()->firstOrFail();
        $this->assertSame(ProjectStatus::Draft, $project->status);
        $this->assertNull($project->published_at);
        $this->assertSame('Portfolio', $project->translation('en')->title);
        $this->assertNull($project->translation('ar'));
        $this->assertSame([$category->id], $project->categories->modelKeys());
    }

    public function test_publishing_with_one_language_is_refused(): void
    {
        Livewire::test(CreateProject::class)
            ->fillForm(['status' => 'published', 'sort_order' => 0, 'translations' => ['en' => $this->content('alone', 'Alone')]])
            ->call('create')
            ->assertHasFormErrors(['status', 'translations.ar.title'])
            ->assertNotified();

        $this->assertSame(0, Project::query()->count());
    }

    public function test_slugs_are_unique_per_language(): void
    {
        $this->englishProject('taken');

        Livewire::test(CreateProject::class)
            ->fillForm(['status' => 'draft', 'sort_order' => 0, 'translations' => ['en' => $this->content('taken', 'Copy')]])
            ->call('create')
            ->assertHasFormErrors(['translations.en.slug']);

        $this->assertSame(1, Project::query()->count());
    }

    public function test_editing_keeps_saved_languages_and_publishes_when_complete(): void
    {
        $project = $this->englishProject('bilingual', ['status' => ProjectStatus::Draft, 'published_at' => null]);

        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
            ->assertFormSet(['status' => 'draft', 'translations.en.slug' => 'bilingual'])
            ->fillForm(['translations.en.title' => '', 'translations.en.slug' => ''])
            ->call('save')
            ->assertHasFormErrors(['translations.en.title', 'translations.en.slug'])
            ->assertActionExists('delete');

        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
            ->fillForm(['status' => 'published', 'translations.ar' => $this->content('thunai', 'ثنائي')])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(ProjectStatus::Published, $project->fresh()->status);
        $this->assertNotNull($project->fresh()->published_at);
    }

    public function test_the_publish_action_refuses_incomplete_content(): void
    {
        $project = $this->englishProject('english-only', ['status' => ProjectStatus::Draft, 'published_at' => null]);

        Livewire::test(ListProjects::class)
            ->callTableAction('toggleStatus', $project)
            ->assertNotified(__('admin.projects.publish_blocked'));

        $this->assertSame(ProjectStatus::Draft, $project->fresh()->status);
    }

    public function test_categories_are_created_and_edited_with_the_shared_rules(): void
    {
        $existing = $this->makeCategory(['en' => ['name' => 'Web', 'slug' => 'web'], 'ar' => ['name' => 'ويب', 'slug' => 'web-ar']]);

        Livewire::test(CreateCategory::class)
            ->fillForm(['sort_order' => 1, 'translations' => ['en' => ['name' => 'Web', 'slug' => 'web']]])
            ->call('create')
            ->assertHasFormErrors(['translations.en.name', 'translations.en.slug']);

        Livewire::test(CreateCategory::class)
            ->fillForm(['sort_order' => 1, 'translations' => ['en' => ['name' => 'Mobile', 'slug' => 'mobile']]])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame(2, Category::query()->count());

        Livewire::test(EditCategory::class, ['record' => $existing->getRouteKey()])
            ->fillForm(['translations.ar.name' => '', 'translations.ar.slug' => ''])
            ->call('save')
            ->assertHasFormErrors(['translations.ar.name'])
            ->assertActionExists('delete');

        Livewire::test(ListCategories::class)->assertTableActionExists('delete')->assertCanSeeTableRecords([$existing]);
    }

    public function test_technology_visibility_is_only_chosen_when_creating(): void
    {
        Livewire::test(CreateTechnology::class)
            ->fillForm(['name' => 'Vue', 'slug' => 'vue', 'group' => 'frontend', 'sort_order' => 0])
            ->call('create')
            ->assertHasNoFormErrors();

        $vue = Technology::query()->where('slug', 'vue')->firstOrFail();
        $this->assertFalse($vue->is_visible);

        Livewire::test(EditTechnology::class, ['record' => $vue->getRouteKey()])
            ->assertFormFieldHidden('is_visible')
            ->fillForm(['name' => 'Vue.js'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertActionExists('delete');

        $this->assertFalse($vue->fresh()->is_visible);
        $this->assertSame('Vue.js', $vue->fresh()->name);

        Livewire::test(CreateTechnology::class)
            ->fillForm(['name' => 'Bad', 'slug' => 'Bad Slug', 'group' => 'frontend', 'icon' => '<svg>', 'sort_order' => 0])
            ->call('create')
            ->assertHasFormErrors(['slug', 'icon']);

        Livewire::test(ListTechnologies::class)
            ->filterTable('is_visible', false)
            ->assertCanSeeTableRecords([$vue->fresh()]);
    }

    public function test_the_dashboard_counts_real_records(): void
    {
        $this->englishProject('one');
        $this->englishProject('two', ['status' => ProjectStatus::Draft, 'published_at' => null]);
        $this->makeTechnology('Laravel');
        $this->makeTechnology('Old', visible: false);
        ContactMessage::factory()->count(2)->create();

        Livewire::test(AdminStatsOverview::class)
            ->assertSee(__('admin.dashboard.projects_description', ['published' => 1, 'drafts' => 1]))
            ->assertSee(__('admin.dashboard.technologies_description', ['visible' => 1, 'hidden' => 1]))
            ->assertSee(__('admin.dashboard.messages_description', ['total' => 2]));
    }

    public function test_pages_follow_the_arabic_interface_language(): void
    {
        $this->withSession(['admin_locale' => 'ar'])
            ->get('/admin/projects')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('المشاريع');
    }
}
