<?php

namespace Tests\Feature\Api;

use App\Enums\ProjectStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\Concerns\BuildsProjects;
use Tests\TestCase;

class ProjectDetailApiTest extends TestCase
{
    use BuildsProjects, RefreshDatabase;

    private const NOT_FOUND = ['message' => 'Resource not found.'];

    public function test_returns_the_project_in_the_requested_locale(): void
    {
        $this->makeProject([
            'en' => ['slug' => 'portfolio-site', 'title' => 'Portfolio Site', 'short_description' => 'Short EN', 'description' => 'Long EN', 'problem' => 'Problem EN', 'solution' => null],
            'ar' => ['slug' => 'mawqi-shakhsi', 'title' => 'موقع شخصي', 'short_description' => 'وصف قصير', 'description' => 'وصف طويل', 'problem' => null, 'solution' => 'حل'],
        ], ['live_url' => 'https://example.com', 'published_at' => '2026-10-20 10:00:00']);

        $this->getJson('/api/projects/portfolio-site?locale=en')
            ->assertOk()
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.title', 'Portfolio Site')
            ->assertJsonPath('data.description', 'Long EN')
            ->assertJsonPath('data.problem', 'Problem EN')
            ->assertJsonPath('data.solution', null)
            ->assertJsonPath('data.links', ['github' => null, 'live' => 'https://example.com'])
            ->assertJsonPath('data.published_at', '2026-10-20T10:00:00Z')
            ->assertDontSee('وصف طويل');

        $this->getJson('/api/projects/mawqi-shakhsi?locale=ar')
            ->assertOk()
            ->assertJsonPath('data.locale', 'ar')
            ->assertJsonPath('data.title', 'موقع شخصي')
            ->assertJsonPath('data.solution', 'حل')
            ->assertDontSee('Long EN');
    }

    public function test_exposes_only_the_approved_fields(): void
    {
        $this->englishProject('field-check', ['sort_order' => 3]);

        $data = $this->getJson('/api/projects/field-check')->assertOk()->json('data');

        $this->assertSame([
            'slug', 'locale', 'title', 'short_description', 'description', 'problem', 'solution', 'is_featured',
            'cover', 'images', 'categories', 'technologies', 'links', 'published_at', 'alternates', 'seo',
        ], array_keys($data));
        foreach (['id', 'status', 'sort_order', 'project_id', 'translations', 'cover_image_path', 'created_at', 'updated_at'] as $field) {
            $this->assertArrayNotHasKey($field, $data);
        }
    }

    public function test_alternates_point_only_to_existing_translations(): void
    {
        $this->makeProject(['en' => ['slug' => 'bilingual-en'], 'ar' => ['slug' => 'bilingual-ar']]);
        $this->makeProject(['en' => ['slug' => 'english-only']]);

        $this->getJson('/api/projects/bilingual-en?locale=en')->assertJsonPath('data.alternates', ['en' => 'bilingual-en', 'ar' => 'bilingual-ar']);
        $this->getJson('/api/projects/bilingual-ar?locale=ar')->assertJsonPath('data.alternates', ['en' => 'bilingual-en', 'ar' => 'bilingual-ar']);
        $this->getJson('/api/projects/english-only?locale=en')->assertJsonPath('data.alternates', ['en' => 'english-only', 'ar' => null]);
    }

    public function test_returns_one_generic_404_for_every_non_visible_case(): void
    {
        $this->makeProject(['en' => ['slug' => 'draft-project']], ['status' => ProjectStatus::Draft, 'published_at' => null]);
        $this->makeProject(['en' => ['slug' => 'english-only-project']]);
        $this->makeProject(['en' => ['slug' => 'shared-en'], 'ar' => ['slug' => 'shared-ar']]);

        $cases = [
            'missing' => '/api/projects/does-not-exist',
            'draft' => '/api/projects/draft-project',
            'no translation in locale' => '/api/projects/english-only-project?locale=ar',
            'slug of the other locale (ar slug, en request)' => '/api/projects/shared-ar?locale=en',
            'slug of the other locale (en slug, ar request)' => '/api/projects/shared-en?locale=ar',
            'malformed slug' => '/api/projects/Not_A_Valid_Slug',
        ];

        foreach ($cases as $case => $uri) {
            $response = $this->getJson($uri);
            $this->assertSame(404, $response->status(), $case);
            $response->assertExactJson(self::NOT_FOUND)->assertDontSee('App\\\\Models')->assertDontSee('Project]');
        }
    }

    public function test_locale_is_validated(): void
    {
        $this->englishProject('locale-check');

        $this->getJson('/api/projects/locale-check')->assertOk()->assertJsonPath('data.locale', 'en');
        $this->getJson('/api/projects/locale-check?locale=fr')->assertStatus(422)->assertJsonValidationErrors('locale');
    }

    public function test_gallery_returns_only_existing_files_with_translated_or_title_alt(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('projects/one.webp', 'bytes');
        Storage::disk('public')->put('projects/two.webp', 'bytes');

        $project = $this->makeProject(['en' => ['slug' => 'gallery', 'title' => 'Gallery Project'], 'ar' => ['slug' => 'maard', 'title' => 'معرض']]);
        $second = $project->images()->create(['path' => 'projects/two.webp', 'width' => 800, 'height' => 600, 'sort_order' => 2]);
        $first = $project->images()->create(['path' => 'projects/one.webp', 'width' => 1280, 'height' => 720, 'sort_order' => 1]);
        $project->images()->create(['path' => 'projects/missing.webp', 'width' => 1, 'height' => 1, 'sort_order' => 3]);
        $project->images()->create(['path' => '../../.env', 'width' => 1, 'height' => 1, 'sort_order' => 4]);
        $first->translations()->create(['locale' => 'en', 'alt_text' => 'First image alt']);
        $first->translations()->create(['locale' => 'ar', 'alt_text' => 'نص بديل']);

        $this->getJson('/api/projects/gallery?locale=en')->assertJsonPath('data.images', [
            ['url' => '/storage/projects/one.webp', 'alt' => 'First image alt', 'width' => 1280, 'height' => 720],
            ['url' => '/storage/projects/two.webp', 'alt' => 'Gallery Project', 'width' => 800, 'height' => 600],
        ]);

        $this->getJson('/api/projects/maard?locale=ar')
            ->assertJsonPath('data.images.0.alt', 'نص بديل')
            ->assertJsonPath('data.images.1.alt', 'معرض');

        $this->assertNotNull($second);
    }

    public function test_cover_and_seo_use_only_real_values(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('projects/cover.jpg', 'bytes');

        $this->makeProject(['en' => ['slug' => 'with-cover', 'title' => 'Cover Project', 'short_description' => 'SEO description', 'cover_image_alt' => 'Cover alt']], ['cover_image_path' => 'projects/cover.jpg']);
        $this->makeProject(['en' => ['slug' => 'missing-cover']], ['cover_image_path' => 'projects/nope.jpg']);
        $this->makeProject(['en' => ['slug' => 'unsafe-cover']], ['cover_image_path' => 'C:/Windows/win.ini']);

        $this->getJson('/api/projects/with-cover')
            ->assertJsonPath('data.cover', ['url' => '/storage/projects/cover.jpg', 'alt' => 'Cover alt'])
            ->assertJsonPath('data.seo', ['title' => 'Cover Project', 'description' => 'SEO description', 'image_url' => '/storage/projects/cover.jpg']);

        $this->getJson('/api/projects/missing-cover')->assertJsonPath('data.cover', null)->assertJsonPath('data.seo.image_url', null);
        $this->getJson('/api/projects/unsafe-cover')->assertJsonPath('data.cover', null);
    }

    public function test_hidden_technologies_and_untranslated_categories_are_not_listed(): void
    {
        $project = $this->makeProject(['en' => ['slug' => 'relations-en'], 'ar' => ['slug' => 'relations-ar']]);
        $project->technologies()->attach([$this->makeTechnology('Laravel')->id, $this->makeTechnology('Secret Tool', visible: false)->id]);
        $project->categories()->attach($this->makeCategory(['en' => ['name' => 'Web', 'slug' => 'web']])->id);

        $this->getJson('/api/projects/relations-en?locale=en')
            ->assertJsonPath('data.technologies', [['slug' => 'laravel', 'name' => 'Laravel']])
            ->assertJsonPath('data.categories', [['slug' => 'web', 'name' => 'Web']])
            ->assertDontSee('Secret Tool');

        $this->getJson('/api/projects/relations-ar?locale=ar')->assertJsonPath('data.categories', []);
    }

    public function test_write_methods_are_not_allowed(): void
    {
        $this->englishProject('read-only');

        foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $method) {
            $this->{$method}('/api/projects/read-only')->assertStatus(405);
            $this->{$method}('/api/projects')->assertStatus(405);
        }

        $this->getJson('/api/projects/read-only')->assertOk();
    }
}
