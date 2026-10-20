<?php

namespace Tests\Feature\Api;

use App\Enums\ProjectStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\Concerns\BuildsProjects;
use Tests\TestCase;

class ProjectsApiTest extends TestCase
{
    use BuildsProjects, RefreshDatabase;

    private const ITEM = ['slug', 'title', 'short_description', 'is_featured', 'cover', 'categories', 'technologies', 'links' => ['github', 'live'], 'published_at'];

    public function test_lists_only_published_projects_translated_in_the_requested_locale(): void
    {
        $this->makeProject(['en' => ['slug' => 'both-en'], 'ar' => ['slug' => 'both-ar']]);
        $this->makeProject(['en' => ['slug' => 'english-only']]);
        $this->makeProject(['ar' => ['slug' => 'arabic-only']]);
        $this->makeProject(['en' => ['slug' => 'draft-en'], 'ar' => ['slug' => 'draft-ar']], ['status' => ProjectStatus::Draft, 'published_at' => null]);

        $en = $this->getJson('/api/projects?locale=en')->assertOk()->json('data.*.slug');
        $this->assertEqualsCanonicalizing(['both-en', 'english-only'], $en);

        $ar = $this->getJson('/api/projects?locale=ar')->assertOk()->json('data.*.slug');
        $this->assertEqualsCanonicalizing(['both-ar', 'arabic-only'], $ar);
    }

    public function test_defaults_to_english_and_rejects_unsupported_locales(): void
    {
        $this->englishProject('hello-world');

        $this->getJson('/api/projects')->assertOk()->assertJsonPath('data.0.slug', 'hello-world');
        $this->getJson('/api/projects?locale=fr')->assertStatus(422)->assertJsonValidationErrors('locale');
        // Malformed input must be a validation error, never a server error.
        $this->getJson('/api/projects?locale[]=en&category=web')->assertStatus(422)->assertJsonValidationErrors('locale');
    }

    public function test_drafts_never_leak_in_any_form(): void
    {
        $this->makeProject(['en' => ['slug' => 'secret-draft', 'title' => 'Unreleased Secret Title']], ['status' => ProjectStatus::Draft, 'published_at' => null]);

        $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertDontSee('Unreleased Secret Title')
            ->assertDontSee('secret-draft');
    }

    public function test_orders_featured_first_then_sort_order_then_newest_then_id(): void
    {
        $this->englishProject('plain-order-1', ['sort_order' => 1, 'published_at' => '2026-01-01 00:00:00']);
        $this->englishProject('featured-order-5', ['is_featured' => true, 'sort_order' => 5, 'published_at' => '2026-01-01 00:00:00']);
        $this->englishProject('plain-order-0-old', ['sort_order' => 0, 'published_at' => '2025-01-01 00:00:00']);
        $this->englishProject('plain-order-0-new', ['sort_order' => 0, 'published_at' => '2026-06-01 00:00:00']);
        $this->englishProject('featured-order-1', ['is_featured' => true, 'sort_order' => 1, 'published_at' => '2026-01-01 00:00:00']);
        $tieA = $this->englishProject('tie-a', ['sort_order' => 9, 'published_at' => '2026-01-01 00:00:00']);
        $tieB = $this->englishProject('tie-b', ['sort_order' => 9, 'published_at' => '2026-01-01 00:00:00']);
        $this->assertGreaterThan($tieA->id, $tieB->id);

        $this->getJson('/api/projects?per_page=24')->assertJsonPath('data.*.slug', [
            'featured-order-1', 'featured-order-5',
            'plain-order-0-new', 'plain-order-0-old', 'plain-order-1',
            'tie-b', 'tie-a',
        ]);
    }

    public function test_paginates_with_standard_links_and_meta(): void
    {
        foreach (range(1, 11) as $i) {
            $this->englishProject("project-{$i}", ['sort_order' => $i]);
        }

        $this->getJson('/api/projects')
            ->assertJsonCount(9, 'data')
            ->assertJsonPath('meta.per_page', 9)
            ->assertJsonPath('meta.total', 11)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonStructure(['data' => ['*' => self::ITEM], 'links' => ['first', 'last', 'prev', 'next'], 'meta' => ['current_page', 'per_page', 'total', 'last_page']]);

        $this->getJson('/api/projects?per_page=5&page=3')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'project-11')
            ->assertJsonPath('meta.current_page', 3)
            ->assertJsonPath('links.next', null);

        // A page beyond the last one is a valid, empty page (not an error).
        $this->getJson('/api/projects?per_page=5&page=4')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 11);

        // Links keep the query string (locale, filters).
        $this->assertStringContainsString('locale=en', (string) $this->getJson('/api/projects?locale=en&per_page=5')->json('links.next'));
    }

    public function test_rejects_invalid_pagination_parameters(): void
    {
        foreach (['per_page=0', 'per_page=25', 'per_page=abc', 'page=0', 'page=-1', 'page=x'] as $query) {
            $this->getJson("/api/projects?{$query}")->assertStatus(422);
        }

        $this->getJson('/api/projects?per_page=24')->assertOk();
        $this->getJson('/api/projects?per_page=1')->assertOk();
    }

    public function test_filters_by_category_slug_in_the_requested_locale(): void
    {
        $web = $this->makeCategory(['en' => ['name' => 'Web Applications', 'slug' => 'web-applications'], 'ar' => ['name' => 'تطبيقات', 'slug' => 'tatbiqat-web']]);
        $inCategory = $this->makeProject(['en' => ['slug' => 'in-category'], 'ar' => ['slug' => 'fi-tasnif']]);
        $this->makeProject(['en' => ['slug' => 'other-project'], 'ar' => ['slug' => 'mashrou-akhar']]);
        $inCategory->categories()->attach($web);

        $this->getJson('/api/projects?locale=en&category=web-applications')->assertJsonPath('data.*.slug', ['in-category']);
        $this->getJson('/api/projects?locale=ar&category=tatbiqat-web')->assertJsonPath('data.*.slug', ['fi-tasnif']);

        // The English slug is not valid for Arabic, and vice versa.
        $this->getJson('/api/projects?locale=ar&category=web-applications')->assertStatus(422)->assertJsonValidationErrors('category');
        $this->getJson('/api/projects?locale=en&category=tatbiqat-web')->assertStatus(422)->assertJsonValidationErrors('category');
    }

    public function test_rejects_unknown_or_untranslated_categories(): void
    {
        $this->makeCategory(['en' => ['name' => 'English Only', 'slug' => 'english-only']]);

        $this->getJson('/api/projects?category=does-not-exist')
            ->assertStatus(422)
            ->assertJsonPath('errors.category.0', 'The selected category does not exist in the requested language.');
        $this->getJson('/api/projects?locale=ar&category=english-only')->assertStatus(422)->assertJsonValidationErrors('category');
        $this->getJson('/api/projects?category=Not_A_Slug')->assertStatus(422)->assertJsonValidationErrors('category');
        $this->getJson('/api/projects?category[]=english-only')->assertStatus(422)->assertJsonValidationErrors('category');
    }

    public function test_filters_by_visible_technology_and_rejects_unknown_or_hidden_ones(): void
    {
        $laravel = $this->makeTechnology('Laravel');
        $hidden = $this->makeTechnology('Hidden Tech', visible: false);
        $withLaravel = $this->englishProject('with-laravel');
        $this->englishProject('without-laravel');
        $withLaravel->technologies()->attach([$laravel->id, $hidden->id]);

        $this->getJson('/api/projects?technology=laravel')->assertJsonPath('data.*.slug', ['with-laravel']);

        $this->getJson('/api/projects?technology=hidden-tech')
            ->assertStatus(422)
            ->assertJsonPath('errors.technology.0', 'The selected technology does not exist.');
        $this->getJson('/api/projects?technology=does-not-exist')->assertStatus(422)->assertJsonValidationErrors('technology');

        // A hidden technology is never listed on a project either.
        $this->getJson('/api/projects?technology=laravel')->assertJsonPath('data.0.technologies', [['slug' => 'laravel', 'name' => 'Laravel']]);
    }

    public function test_featured_filter(): void
    {
        $this->englishProject('featured-one', ['is_featured' => true]);
        $this->englishProject('regular-one');

        $this->getJson('/api/projects?featured=1')->assertJsonPath('data.*.slug', ['featured-one']);
        $this->getJson('/api/projects?featured=0')->assertJsonCount(2, 'data');
        $this->getJson('/api/projects?featured=yes')->assertStatus(422)->assertJsonValidationErrors('featured');
    }

    public function test_filters_can_be_combined(): void
    {
        $category = $this->makeCategory(['en' => ['name' => 'Dashboards', 'slug' => 'dashboards']]);
        $vue = $this->makeTechnology('Vue');

        $match = $this->englishProject('match-all', ['is_featured' => true]);
        $match->categories()->attach($category);
        $match->technologies()->attach($vue);

        $notFeatured = $this->englishProject('not-featured');
        $notFeatured->categories()->attach($category);
        $notFeatured->technologies()->attach($vue);

        $noTech = $this->englishProject('no-tech', ['is_featured' => true]);
        $noTech->categories()->attach($category);

        $this->getJson('/api/projects?category=dashboards&technology=vue&featured=1')->assertJsonPath('data.*.slug', ['match-all']);
        $this->getJson('/api/projects?category=dashboards&technology=vue')->assertJsonCount(2, 'data');
    }

    public function test_items_expose_only_public_fields(): void
    {
        $this->englishProject('public-fields', ['sort_order' => 42, 'github_url' => 'https://github.com/example/repo']);

        $item = $this->getJson('/api/projects')->assertJsonStructure(['data' => ['*' => self::ITEM]])->json('data.0');

        $this->assertSame(array_keys($item), ['slug', 'title', 'short_description', 'is_featured', 'cover', 'categories', 'technologies', 'links', 'published_at']);
        foreach (['id', 'status', 'sort_order', 'project_id', 'translations', 'cover_image_path', 'created_at', 'updated_at', 'description'] as $field) {
            $this->assertArrayNotHasKey($field, $item);
        }
        $this->assertSame(['github' => 'https://github.com/example/repo', 'live' => null], $item['links']);
    }

    public function test_links_are_never_invented_and_unsafe_ones_are_dropped(): void
    {
        $this->englishProject('bad-links', ['github_url' => 'javascript:alert(1)', 'live_url' => null]);

        $this->getJson('/api/projects')->assertJsonPath('data.0.links', ['github' => null, 'live' => null]);
    }

    public function test_cover_url_only_for_existing_safe_files_with_translated_alt(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('projects/cover.jpg', 'bytes');

        $this->makeProject(['en' => ['slug' => 'with-cover', 'title' => 'With Cover', 'cover_image_alt' => 'Cover alt text']], ['cover_image_path' => 'projects/cover.jpg', 'sort_order' => 1]);
        $this->makeProject(['en' => ['slug' => 'alt-fallback', 'title' => 'Alt Fallback']], ['cover_image_path' => 'projects/cover.jpg', 'sort_order' => 2]);
        $this->makeProject(['en' => ['slug' => 'missing-file']], ['cover_image_path' => 'projects/missing.jpg', 'sort_order' => 3]);
        $this->makeProject(['en' => ['slug' => 'unsafe-path']], ['cover_image_path' => '../.env', 'sort_order' => 4]);

        $data = collect($this->getJson('/api/projects')->json('data'))->keyBy('slug');

        $this->assertSame(['url' => '/storage/projects/cover.jpg', 'alt' => 'Cover alt text'], $data['with-cover']['cover']);
        $this->assertSame(['url' => '/storage/projects/cover.jpg', 'alt' => 'Alt Fallback'], $data['alt-fallback']['cover']);
        $this->assertNull($data['missing-file']['cover']);
        $this->assertNull($data['unsafe-path']['cover']);
    }

    public function test_categories_and_technologies_are_returned_in_the_requested_locale(): void
    {
        $category = $this->makeCategory(['en' => ['name' => 'Landing Pages', 'slug' => 'landing-pages'], 'ar' => ['name' => 'صفحات', 'slug' => 'safahat']]);
        $untranslated = $this->makeCategory(['en' => ['name' => 'English Only', 'slug' => 'english-only-cat']]);
        $tech = $this->makeTechnology('MySQL');
        $tech->translations()->create(['locale' => 'ar', 'display_name' => 'ماي إس كيو إل', 'description' => null]);

        $project = $this->makeProject(['en' => ['slug' => 'localized-en'], 'ar' => ['slug' => 'localized-ar']]);
        $project->categories()->attach([$category->id, $untranslated->id]);
        $project->technologies()->attach($tech);

        $this->getJson('/api/projects?locale=en')
            ->assertJsonPath('data.0.categories', [['slug' => 'landing-pages', 'name' => 'Landing Pages'], ['slug' => 'english-only-cat', 'name' => 'English Only']])
            ->assertJsonPath('data.0.technologies', [['slug' => 'mysql', 'name' => 'MySQL']]);

        $this->getJson('/api/projects?locale=ar')
            ->assertJsonPath('data.0.categories', [['slug' => 'safahat', 'name' => 'صفحات']])
            ->assertJsonPath('data.0.technologies', [['slug' => 'mysql', 'name' => 'ماي إس كيو إل']])
            ->assertDontSee('Landing Pages')
            ->assertDontSee('English Only');
    }

    public function test_query_count_does_not_grow_with_the_number_of_projects(): void
    {
        $category = $this->makeCategory(['en' => ['name' => 'Tools', 'slug' => 'tools']]);
        $tech = $this->makeTechnology('PHP');

        $make = function (int $count) use ($category, $tech) {
            foreach (range(1, $count) as $i) {
                $project = $this->englishProject('p-'.uniqid());
                $project->categories()->attach($category);
                $project->technologies()->attach($tech);
            }
        };

        $countQueries = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/projects?per_page=24')->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $make(1);
        $withOne = $countQueries();
        $make(7);
        $withEight = $countQueries();

        $this->assertSame($withOne, $withEight, 'eager loading keeps the query count constant');
    }

    public function test_empty_database_returns_an_empty_page(): void
    {
        $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }
}
