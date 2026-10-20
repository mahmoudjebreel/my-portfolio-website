<?php

namespace Tests\Feature\Filament;

use App\Enums\MessageStatus;
use App\Filament\Pages\MediaLibraryPage;
use App\Filament\Pages\SiteSettingsPage;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Models\ContactMessage;
use App\Models\ProjectImage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\ReplyLink;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Api\Concerns\BuildsProjects;
use Tests\TestCase;

/**
 * Filament inbox, media library and site settings (F4). Test database only (TestCase guard
 * + RefreshDatabase); fake public disk.
 */
class FilamentInboxMediaSettingsTest extends TestCase
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

    public function test_the_new_pages_open_only_for_administrators(): void
    {
        $message = ContactMessage::factory()->create();
        Storage::disk('public')->put('projects/one.png', UploadedFile::fake()->image('one.png', 20, 10)->get());
        $image = ProjectImage::factory()->create(['project_id' => $this->englishProject('with-image')->id, 'path' => 'projects/one.png']);
        $pages = ['/admin/contact-messages', "/admin/contact-messages/{$message->id}", '/admin/media', '/admin/settings', "/admin/media-preview/gallery/{$image->id}"];

        foreach ($pages as $page) {
            $this->get($page)->assertOk();
        }

        $this->actingAs(User::factory()->create(), 'web');
        foreach ($pages as $page) {
            $this->get($page)->assertForbidden();
        }

        auth('web')->logout();
        $this->get("/admin/media-preview/gallery/{$image->id}")->assertRedirect('/admin/login');
    }

    public function test_the_inbox_lists_newest_first_searches_and_only_deletes(): void
    {
        $old = ContactMessage::factory()->create(['name' => 'Sara', 'subject' => 'Website', 'created_at' => now()->subDays(2)]);
        $new = ContactMessage::factory()->create(['name' => 'Omar', 'subject' => 'Logo', 'status' => MessageStatus::Read, 'read_at' => now(), 'created_at' => now()->subDay()]);

        Livewire::test(ListContactMessages::class)
            ->assertCanSeeTableRecords([$new, $old], inOrder: true)
            ->searchTable('Sara')
            ->assertCanSeeTableRecords([$old])
            ->assertCanNotSeeTableRecords([$new])
            ->assertTableActionExists('delete')
            ->assertTableActionDoesNotExist('edit')
            ->assertActionDoesNotExist('create');

        Livewire::test(ListContactMessages::class)
            ->filterTable('status', 'read')
            ->assertCanSeeTableRecords([$new])
            ->assertCanNotSeeTableRecords([$old]);

        $this->assertSame('1', ContactMessageResource::getNavigationBadge());
        $this->assertFalse(ContactMessageResource::canCreate());
    }

    public function test_a_message_is_shown_as_plain_text_and_opening_it_changes_nothing(): void
    {
        $message = ContactMessage::factory()->create(['message' => "<script>alert('x')</script>\nSecond line", 'subject' => '<b>Hi</b>']);

        $this->get("/admin/contact-messages/{$message->id}")
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee("<script>alert('x')</script>", false)
            ->assertDontSee('<b>Hi</b>', false);

        $this->assertSame(MessageStatus::Unread, $message->fresh()->status);
        $this->assertNull($message->fresh()->read_at);
    }

    public function test_marking_read_and_unread_from_the_message_page(): void
    {
        $message = ContactMessage::factory()->create();

        Livewire::test(ViewContactMessage::class, ['record' => $message->getRouteKey()])
            ->callAction('toggleRead')
            ->assertNotified(__('admin.messages.marked_read'));
        $this->assertSame(MessageStatus::Read, $message->fresh()->status);
        $this->assertNotNull($message->fresh()->read_at);

        Livewire::test(ViewContactMessage::class, ['record' => $message->getRouteKey()])
            ->callAction('toggleRead');
        $this->assertSame(MessageStatus::Unread, $message->fresh()->status);
        $this->assertNull($message->fresh()->read_at);

        $archived = ContactMessage::factory()->create(['status' => MessageStatus::Archived]);
        Livewire::test(ViewContactMessage::class, ['record' => $archived->getRouteKey()])
            ->assertActionHidden('toggleRead');
    }

    public function test_the_reply_link_is_safe_and_only_offered_for_valid_addresses(): void
    {
        // "?", "=" and "&" are valid in the local part; encoded, they cannot add headers.
        $this->assertSame('mailto:a%3Fcc%3Devil%26bcc%3Dx@example.com?subject=Re%3A%20Hi', ReplyLink::for('a?cc=evil&bcc=x@example.com', 'Hi'));
        $this->assertNull(ReplyLink::for('a?cc=evil@x.com&bcc=y@example.com', 'Hi'), 'two @ signs are not an email address');
        $this->assertNull(ReplyLink::for('not an email', 'Hi'));

        $invalid = ContactMessage::factory()->create(['email' => 'not an email']);
        Livewire::test(ViewContactMessage::class, ['record' => $invalid->getRouteKey()])
            ->assertActionHidden('reply');

        $valid = ContactMessage::factory()->create(['email' => 'sara@example.com', 'subject' => 'Website']);
        Livewire::test(ViewContactMessage::class, ['record' => $valid->getRouteKey()])
            ->assertActionVisible('reply')
            ->assertActionHasUrl('reply', 'mailto:sara@example.com?subject=Re%3A%20Website');
    }

    public function test_the_media_page_lists_images_and_the_preview_is_safe(): void
    {
        Storage::disk('public')->put('projects/one.png', UploadedFile::fake()->image('one.png', 20, 10)->get());
        Storage::disk('public')->put('projects/evil.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $project = $this->englishProject('gallery', ['cover_image_path' => 'projects/one.png']);
        $image = ProjectImage::factory()->create(['project_id' => $project->id, 'path' => 'projects/one.png']);
        $svg = ProjectImage::factory()->create(['project_id' => $project->id, 'path' => 'projects/evil.svg']);
        $traversal = ProjectImage::factory()->create(['project_id' => $project->id, 'path' => '../../.env']);

        Livewire::test(MediaLibraryPage::class)
            ->assertCanSeeTableRecords(["cover-{$project->id}", "gallery-{$image->id}", "gallery-{$svg->id}", "gallery-{$traversal->id}"])
            ->assertDontSee('projects/one.png')
            ->filterTable('kind', 'cover')
            ->assertCanSeeTableRecords(["cover-{$project->id}"])
            ->assertCanNotSeeTableRecords(["gallery-{$image->id}"]);

        $this->get("/admin/media-preview/gallery/{$image->id}")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get("/admin/media-preview/gallery/{$svg->id}")->assertNotFound();
        $this->get("/admin/media-preview/gallery/{$traversal->id}")->assertNotFound();
    }

    public function test_an_upload_is_re_encoded_and_added_to_the_gallery(): void
    {
        $project = $this->englishProject('upload-target');

        Livewire::test(MediaLibraryPage::class)
            ->callAction('upload', data: [
                'project_id' => $project->id,
                'file' => UploadedFile::fake()->image('../../evil.png', 640, 480),
                'alt' => ['en' => 'Home page', 'ar' => null],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified(__('admin.media.uploaded'));

        $image = ProjectImage::query()->firstOrFail();
        $this->assertMatchesRegularExpression("#^projects/{$project->id}/[0-9a-f-]{36}\\.webp$#", $image->path);
        $this->assertSame([640, 480], [$image->width, $image->height]);
        Storage::disk('public')->assertExists($image->path);
        $this->assertSame('Home page', $image->translation('en')->alt_text);
    }

    public function test_an_upload_that_is_not_an_image_is_refused(): void
    {
        $project = $this->englishProject('upload-target');

        Livewire::test(MediaLibraryPage::class)
            ->callAction('upload', data: [
                'project_id' => $project->id,
                'file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            ])
            ->assertHasActionErrors(['file']);

        $this->assertSame(0, ProjectImage::query()->count());
    }

    public function test_settings_are_shown_saved_on_request_and_validated(): void
    {
        $name = SiteSetting::create(['key' => 'display_name', 'value' => null]);
        $name->translations()->create(['locale' => 'en', 'value' => 'Jebreel']);
        $before = SiteSetting::query()->count();

        $page = Livewire::test(SiteSettingsPage::class)
            ->assertSet('data.texts.display_name.en', 'Jebreel');
        $this->assertSame($before, SiteSetting::query()->count(), 'opening the page writes nothing');

        $page->set('data.values.github_url', 'javascript:alert(1)')
            ->call('save')
            ->assertHasErrors(['data.values.github_url']);
        $this->assertNull(SiteSetting::query()->where('key', 'github_url')->first());

        $page->set('data.values.github_url', 'https://github.com/example')
            ->set('data.texts.display_name.ar', 'جبريل')
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified(__('admin.settings.saved'));

        $this->assertSame('https://github.com/example', SiteSetting::query()->where('key', 'github_url')->firstOrFail()->value);
        $this->assertSame('جبريل', $name->fresh()->translation('ar')->value);
    }
}
