<?php

namespace Tests\Feature\Actions;

use App\Actions\Catalog\SaveCategory;
use App\Actions\Catalog\SaveTechnology;
use App\Actions\Messages\ChangeMessageStatus;
use App\Actions\Projects\ChangeProjectStatus;
use App\Actions\Projects\SaveProject;
use App\Actions\Settings\SaveSiteSettings;
use App\Enums\MessageStatus;
use App\Enums\ProjectStatus;
use App\Models\ContactMessage;
use App\Models\Project;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Api\Concerns\BuildsProjects;
use Tests\TestCase;

/**
 * Shared actions (F2) called directly, as the Filament panel will call them.
 * Test database only (TestCase guard + RefreshDatabase).
 */
class ActionsDatabaseTest extends TestCase
{
    use BuildsProjects;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function content(string $slug): array
    {
        return ['title' => ucfirst($slug), 'slug' => $slug, 'short_description' => 'Short', 'description' => 'Long'];
    }

    public function test_publishing_needs_both_languages_and_rolls_back_otherwise(): void
    {
        try {
            (new SaveProject)->handle(null, ['status' => 'published', 'translations' => ['en' => $this->content('only-english'), 'ar' => null]]);
            $this->fail('Publishing one language must be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
            $this->assertArrayHasKey('translations.ar', $exception->errors());
        }
        $this->assertSame(0, Project::query()->count(), 'nothing is kept');

        $project = (new SaveProject)->handle(null, ['status' => 'draft', 'translations' => ['en' => $this->content('draft'), 'ar' => null]]);
        $this->assertNull($project->published_at);

        // Existing English content counts when Arabic is added later.
        (new SaveProject)->handle($project, ['status' => 'published', 'translations' => ['en' => null, 'ar' => $this->content('mashroo')]]);
        $this->assertSame(ProjectStatus::Published, $project->fresh()->status);
        $this->assertNotNull($first = $project->fresh()->published_at);

        (new ChangeProjectStatus)->handle($project->fresh(), ProjectStatus::Draft);
        $this->assertEquals($first, $project->fresh()->published_at, 'first publication date kept');
    }

    public function test_untrusted_columns_are_never_taken_from_the_input(): void
    {
        $project = (new SaveProject)->handle(null, [
            'status' => 'draft',
            'cover_image_path' => '../../.env',
            'published_at' => '2000-01-01',
            'translations' => ['en' => $this->content('safe'), 'ar' => null],
        ]);

        $this->assertNull($project->fresh()->cover_image_path);
        $this->assertNull($project->fresh()->published_at);
    }

    public function test_change_status_refuses_incomplete_content(): void
    {
        $project = $this->englishProject('english-only', ['status' => ProjectStatus::Draft, 'published_at' => null]);

        $this->expectException(ValidationException::class);
        (new ChangeProjectStatus)->handle($project, ProjectStatus::Published);
    }

    public function test_categories_keep_saved_languages(): void
    {
        $category = (new SaveCategory)->handle(null, ['translations' => ['en' => ['name' => 'Web', 'slug' => 'web'], 'ar' => null]]);
        (new SaveCategory)->handle($category, ['sort_order' => 4, 'translations' => ['en' => null, 'ar' => ['name' => 'ويب', 'slug' => 'web-ar']]]);

        $this->assertSame(4, $category->fresh()->sort_order);
        $this->assertSame(2, $category->translations()->count());
        $this->assertSame('Web', $category->translation('en')->name);
    }

    public function test_technology_visibility_is_set_only_when_creating(): void
    {
        $hidden = (new SaveTechnology)->handle(null, ['name' => 'Vue', 'slug' => 'vue', 'group' => 'frontend']);
        $this->assertFalse($hidden->is_visible);

        (new SaveTechnology)->handle($hidden, ['name' => 'Vue.js', 'slug' => 'vue', 'group' => 'frontend', 'is_visible' => true]);
        $this->assertFalse($hidden->fresh()->is_visible);
        $this->assertSame('Vue.js', $hidden->fresh()->name);
    }

    public function test_site_settings_ignore_unknown_keys_and_never_delete(): void
    {
        (new SaveSiteSettings)->handle(
            ['display_name' => ['en' => 'Jebreel', 'ar' => null], 'mail_password' => ['en' => 'x']],
            ['github_url' => 'https://github.com/example', 'profile_image_path' => '../../.env'],
        );

        $this->assertSame(['display_name', 'github_url'], SiteSetting::query()->orderBy('key')->pluck('key')->all());
        $this->assertSame(['en' => 'Jebreel', 'ar' => null], SaveSiteSettings::snapshot()['texts']['display_name']);

        (new SaveSiteSettings)->handle(['display_name' => ['en' => null, 'ar' => null]], []);
        $this->assertSame('', SiteSetting::query()->where('key', 'display_name')->firstOrFail()->translations()->first()->value);
        $this->assertSame('https://github.com/example', SaveSiteSettings::snapshot()['values']['github_url'], 'missing keys untouched');
    }

    public function test_message_read_state(): void
    {
        $message = ContactMessage::factory()->create();

        (new ChangeMessageStatus)->handle($message, MessageStatus::Read);
        $this->assertSame(0, ChangeMessageStatus::unreadCount());
        $this->assertNotNull($message->fresh()->read_at);

        (new ChangeMessageStatus)->handle($message, MessageStatus::Unread);
        $this->assertNull($message->fresh()->read_at);
        $this->assertSame(1, ChangeMessageStatus::unreadCount());
    }
}
