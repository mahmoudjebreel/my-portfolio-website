<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\MediaLibraryPage;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Technologies\Pages\EditTechnology;
use App\Filament\Resources\Technologies\Pages\ListTechnologies;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Project;
use App\Models\ProjectImage;
use App\Models\Technology;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Api\Concerns\BuildsProjects;
use Tests\TestCase;

/**
 * Deleting from the Filament panel, and showing / hiding technologies.
 * Test database only (TestCase guard + RefreshDatabase); fake public disk.
 */
class FilamentDeleteTest extends TestCase
{
    use BuildsProjects;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create(), 'web');
        Filament::setCurrentPanel('admin');
    }

    public function test_deleting_a_project_removes_its_content_and_unused_files_only(): void
    {
        $category = $this->makeCategory(['en' => ['name' => 'Web', 'slug' => 'web']]);
        $technology = $this->makeTechnology('Laravel');
        Storage::disk('public')->put('projects/cover.webp', 'cover');
        Storage::disk('public')->put('projects/own.webp', 'own');
        Storage::disk('public')->put('projects/shared.webp', 'shared');

        $project = $this->englishProject('to-delete', ['cover_image_path' => 'projects/cover.webp']);
        $project->categories()->attach($category);
        $project->technologies()->attach($technology);
        ProjectImage::factory()->create(['project_id' => $project->id, 'path' => 'projects/own.webp']);
        ProjectImage::factory()->create(['project_id' => $project->id, 'path' => 'projects/shared.webp']);
        $other = $this->englishProject('other');
        ProjectImage::factory()->create(['project_id' => $other->id, 'path' => 'projects/shared.webp']);

        $this->getJson('/api/projects/to-delete?locale=en')->assertOk();

        Livewire::test(ListProjects::class)->callTableAction('delete', $project)->assertNotified();

        $this->assertModelMissing($project);
        $this->assertDatabaseMissing('project_translations', ['project_id' => $project->id]);
        $this->assertDatabaseMissing('project_images', ['project_id' => $project->id]);
        $this->assertModelExists($category);
        $this->assertModelExists($technology);
        Storage::disk('public')->assertMissing('projects/cover.webp');
        Storage::disk('public')->assertMissing('projects/own.webp');
        Storage::disk('public')->assertExists('projects/shared.webp');
        $this->getJson('/api/projects/to-delete?locale=en')->assertNotFound();
    }

    public function test_a_project_can_be_deleted_from_its_edit_page_and_in_bulk(): void
    {
        $one = $this->englishProject('one');
        $two = $this->englishProject('two');
        $three = $this->englishProject('three');

        Livewire::test(EditProject::class, ['record' => $one->getRouteKey()])
            ->callAction('delete')
            ->assertRedirect('/admin/projects');
        $this->assertModelMissing($one);

        Livewire::test(ListProjects::class)->callTableBulkAction('delete', [$two, $three]);
        $this->assertSame(0, Project::query()->count());
    }

    public function test_a_linked_category_is_not_deleted_and_an_unlinked_one_is(): void
    {
        $linked = $this->makeCategory(['en' => ['name' => 'Web', 'slug' => 'web']]);
        $this->englishProject('uses-web')->categories()->attach($linked);
        $free = $this->makeCategory(['en' => ['name' => 'Free', 'slug' => 'free']]);

        Livewire::test(ListCategories::class)
            ->callTableAction('delete', $linked)
            ->assertNotified(__('admin.common.delete_blocked'));
        $this->assertModelExists($linked);

        Livewire::test(ListCategories::class)->callTableAction('delete', $free);
        $this->assertModelMissing($free);
        $this->assertDatabaseMissing('category_translations', ['category_id' => $free->id]);

        $another = $this->makeCategory(['en' => ['name' => 'Another', 'slug' => 'another']]);
        Livewire::test(ListCategories::class)->callTableBulkAction('delete', [$linked, $another]);
        $this->assertModelExists($linked);
        $this->assertModelMissing($another);
    }

    public function test_technologies_delete_only_when_unlinked_and_can_be_shown_or_hidden(): void
    {
        $linked = $this->makeTechnology('Laravel');
        $this->englishProject('uses-laravel')->technologies()->attach($linked);
        $hidden = $this->makeTechnology('Vue', visible: false);

        Livewire::test(ListTechnologies::class)
            ->callTableAction('delete', $linked)
            ->assertNotified(__('admin.common.delete_blocked'));
        $this->assertModelExists($linked);

        $this->getJson('/api/technologies?locale=en')->assertJsonMissing(['name' => 'Vue']);
        Livewire::test(ListTechnologies::class)->callTableAction('toggleVisibility', $hidden)->assertNotified(__('admin.technologies.now_visible'));
        $this->assertTrue($hidden->fresh()->is_visible);
        $this->getJson('/api/technologies?locale=en')->assertJsonFragment(['name' => 'Vue']);

        Livewire::test(EditTechnology::class, ['record' => $hidden->getRouteKey()])->callAction('toggleVisibility');
        $this->assertFalse($hidden->fresh()->is_visible);

        Livewire::test(EditTechnology::class, ['record' => $hidden->getRouteKey()])
            ->callAction('delete')
            ->assertRedirect('/admin/technologies');
        $this->assertModelMissing($hidden);
        $this->assertSame(1, Technology::query()->count());
    }

    public function test_messages_can_be_deleted(): void
    {
        [$one, $two, $three] = ContactMessage::factory()->count(3)->create()->all();

        Livewire::test(ListContactMessages::class)->callTableAction('delete', $one);
        $this->assertModelMissing($one);

        Livewire::test(ViewContactMessage::class, ['record' => $two->getRouteKey()])
            ->callAction('delete')
            ->assertRedirect('/admin/contact-messages');
        $this->assertModelMissing($two);

        Livewire::test(ListContactMessages::class)->callTableBulkAction('delete', [$three]);
        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_media_gallery_images_are_deleted_and_covers_removed(): void
    {
        Storage::disk('public')->put('projects/gallery.webp', 'gallery');
        Storage::disk('public')->put('projects/cover.webp', 'cover');
        $project = $this->englishProject('with-media', ['cover_image_path' => 'projects/cover.webp']);
        $image = ProjectImage::factory()->create(['project_id' => $project->id, 'path' => 'projects/gallery.webp']);

        Livewire::test(MediaLibraryPage::class)
            ->callTableAction('delete', "gallery-{$image->id}")
            ->assertNotified(__('admin.media.deleted'));
        $this->assertModelMissing($image);
        Storage::disk('public')->assertMissing('projects/gallery.webp');

        Livewire::test(MediaLibraryPage::class)->callTableAction('delete', "cover-{$project->id}");
        $this->assertNull($project->fresh()->cover_image_path);
        $this->assertModelExists($project);
        Storage::disk('public')->assertMissing('projects/cover.webp');
    }

    public function test_files_outside_the_public_folder_are_never_deleted(): void
    {
        Storage::disk('public')->put('safe.webp', 'kept');
        $project = $this->englishProject('unsafe', ['cover_image_path' => '../../.env']);
        ProjectImage::factory()->create(['project_id' => $project->id, 'path' => '../safe.webp']);

        Livewire::test(ListProjects::class)->callTableAction('delete', $project);

        $this->assertModelMissing($project);
        Storage::disk('public')->assertExists('safe.webp');
        $this->assertFileExists(base_path('.env.example'));
        $this->assertSame(0, Category::query()->count());
    }
}
