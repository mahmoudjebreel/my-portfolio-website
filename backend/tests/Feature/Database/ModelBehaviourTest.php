<?php

namespace Tests\Feature\Database;

use App\Enums\Locale;
use App\Enums\MessageStatus;
use App\Enums\ProjectStatus;
use App\Enums\TechnologyGroup;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\ContactMessage;
use App\Models\Project;
use App\Models\ProjectImage;
use App\Models\ProjectTranslation;
use App\Models\SiteSetting;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ModelBehaviourTest extends TestCase
{
    use RefreshDatabase;

    public function test_translation_helper_is_strict_and_never_falls_back(): void
    {
        $project = Project::factory()->create();
        $en = ProjectTranslation::factory()->for($project)->create();

        $this->assertTrue($project->translation('en')->is($en));
        $this->assertTrue($project->translation(Locale::En)->is($en));
        $this->assertNull($project->translation('ar'));
        $this->assertNull($project->translation(Locale::Ar));

        // Same behaviour when the relation is already eager loaded.
        $project->load('translations');
        $this->assertTrue($project->translation(Locale::En)->is($en));
        $this->assertNull($project->translation(Locale::Ar));
    }

    public function test_translation_helper_returns_the_requested_locale(): void
    {
        $category = Category::factory()->create();
        CategoryTranslation::factory()->for($category)->create(['name' => 'Tools']);
        CategoryTranslation::factory()->for($category)->locale(Locale::Ar)->create(['name' => 'أدوات']);

        $this->assertSame('Tools', $category->translation('en')->name);
        $this->assertSame('أدوات', $category->translation('ar')->name);
    }

    public function test_enum_casts_round_trip(): void
    {
        $project = Project::factory()->create(['status' => ProjectStatus::Published]);
        $technology = Technology::factory()->create(['group' => TechnologyGroup::Database]);
        $message = ContactMessage::factory()->create(['locale' => Locale::Ar, 'status' => MessageStatus::Archived]);
        $translation = CategoryTranslation::factory()->locale(Locale::Ar)->create();

        $this->assertSame(ProjectStatus::Published, $project->fresh()->status);
        $this->assertSame(TechnologyGroup::Database, $technology->fresh()->group);
        $this->assertSame(Locale::Ar, $message->fresh()->locale);
        $this->assertSame(MessageStatus::Archived, $message->fresh()->status);
        $this->assertSame(Locale::Ar, $translation->fresh()->locale);

        // Stored as the plain backing values.
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'status' => 'published']);
        $this->assertDatabaseHas('technologies', ['id' => $technology->id, 'group' => 'database']);
        $this->assertDatabaseHas('contact_messages', ['id' => $message->id, 'locale' => 'ar', 'status' => 'archived']);
    }

    public function test_database_defaults_apply(): void
    {
        $project = Project::create([])->fresh();
        $this->assertSame(ProjectStatus::Draft, $project->status);
        $this->assertFalse($project->is_featured);

        $message = ContactMessage::create([
            'name' => 'Sam',
            'email' => 'sam@example.com',
            'subject' => 'Hello',
            'message' => 'Hi there',
            'locale' => Locale::En,
        ])->fresh();
        $this->assertSame(MessageStatus::Unread, $message->status);

        $technology = Technology::create(['name' => 'Vue', 'slug' => 'vue', 'group' => TechnologyGroup::Frontend])->fresh();
        $this->assertTrue($technology->is_visible);
    }

    public function test_locale_direction(): void
    {
        $this->assertSame('ltr', Locale::En->direction());
        $this->assertSame('rtl', Locale::Ar->direction());
    }

    public function test_is_admin_defaults_to_false_and_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->fresh()->is_admin);

        $sneaky = User::create([
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'secret-password',
            'is_admin' => true,
        ]);

        $this->assertFalse($sneaky->fresh()->is_admin);
        $this->assertDatabaseHas('users', ['id' => $sneaky->id, 'is_admin' => false]);

        $admin = User::factory()->admin()->create();
        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_relationships_and_casts(): void
    {
        $project = Project::factory()->create(['is_featured' => true, 'published_at' => '2026-01-02 03:04:05']);
        $category = Category::factory()->create();
        $technology = Technology::factory()->create(['is_visible' => false]);
        $project->categories()->attach($category);
        $project->technologies()->attach($technology);

        ProjectImage::factory()->for($project)->create(['sort_order' => 3]);
        ProjectImage::factory()->for($project)->create(['sort_order' => 1]);
        ProjectImage::factory()->for($project)->create(['sort_order' => 2]);

        $project = $project->fresh();

        $this->assertTrue($project->is_featured);
        $this->assertInstanceOf(Carbon::class, $project->published_at);
        $this->assertSame([1, 2, 3], $project->images->pluck('sort_order')->all());
        $this->assertTrue($project->categories->first()->is($category));
        $this->assertTrue($project->technologies->first()->is($technology));
        $this->assertTrue($category->projects->first()->is($project));
        $this->assertTrue($technology->fresh()->projects->first()->is($project));
        $this->assertFalse($technology->fresh()->is_visible);
    }

    public function test_site_setting_value_is_json(): void
    {
        $setting = SiteSetting::factory()->create(['key' => 'social', 'value' => ['github' => 'https://github.com/x', 'count' => 2]]);

        // MySQL normalises JSON object key order, so compare without caring about order.
        $this->assertEquals(['github' => 'https://github.com/x', 'count' => 2], $setting->fresh()->value);
    }

    public function test_translation_factories_produce_valid_slugs_and_locales(): void
    {
        $pattern = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

        foreach (ProjectTranslation::factory()->count(10)->create() as $translation) {
            $this->assertMatchesRegularExpression($pattern, $translation->slug);
            $this->assertInstanceOf(Locale::class, $translation->fresh()->locale);
        }

        foreach (CategoryTranslation::factory()->count(10)->create() as $translation) {
            $this->assertMatchesRegularExpression($pattern, $translation->slug);
            $this->assertInstanceOf(Locale::class, $translation->fresh()->locale);
        }

        foreach (Technology::factory()->count(10)->create() as $technology) {
            $this->assertMatchesRegularExpression($pattern, $technology->slug);
        }
    }
}
