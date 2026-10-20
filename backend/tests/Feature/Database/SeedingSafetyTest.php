<?php

namespace Tests\Feature\Database;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\ContactMessage;
use App\Models\Project;
use App\Models\ProjectImage;
use App\Models\ProjectImageTranslation;
use App\Models\ProjectTranslation;
use App\Models\SiteSetting;
use App\Models\SiteSettingTranslation;
use App\Models\Technology;
use App\Models\TechnologyTranslation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeedingSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_no_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function factoryModels(): array
    {
        $models = [
            User::class, Category::class, CategoryTranslation::class, Technology::class,
            TechnologyTranslation::class, Project::class, ProjectTranslation::class,
            ProjectImage::class, ProjectImageTranslation::class, ContactMessage::class,
            SiteSetting::class, SiteSettingTranslation::class,
        ];

        return array_combine(array_map('class_basename', $models), array_map(fn ($m) => [$m], $models));
    }

    #[DataProvider('factoryModels')]
    public function test_factories_refuse_to_run_outside_the_testing_environment(string $model): void
    {
        $this->app['env'] = 'local';

        try {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('test-only');
            $model::factory()->make();
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_factories_still_work_in_the_testing_environment(): void
    {
        $this->assertSame('testing', $this->app->environment());
        $this->assertNotNull(User::factory()->make()->email);
    }
}
