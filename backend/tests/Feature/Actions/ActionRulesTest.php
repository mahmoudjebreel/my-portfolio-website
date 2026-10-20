<?php

namespace Tests\Feature\Actions;

use App\Actions\Catalog\SaveCategory;
use App\Actions\Media\AddGalleryImage;
use App\Actions\Messages\ChangeMessageStatus;
use App\Enums\MessageStatus;
use App\Models\ContactMessage;
use App\Models\Project;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Business rules of the shared actions (F2) that apply before any database access.
 * Every test fails if a query is attempted.
 */
class ActionRulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::listen(fn ($query) => $this->fail('Unexpected database query: '.$query->sql));
    }

    public function test_archived_messages_and_archiving_are_refused(): void
    {
        $archived = new ContactMessage;
        $archived->status = MessageStatus::Archived;

        try {
            (new ChangeMessageStatus)->handle($archived, MessageStatus::Read);
            $this->fail('An archived message must not change.');
        } catch (DomainException) {
            $this->assertSame(MessageStatus::Archived, $archived->status);
        }

        $unread = new ContactMessage;
        $unread->status = MessageStatus::Unread;
        $this->expectException(DomainException::class);
        (new ChangeMessageStatus)->handle($unread, MessageStatus::Archived);
    }

    public function test_an_invalid_upload_is_refused_before_anything_is_written(): void
    {
        Storage::fake('public');
        $project = new Project;
        $project->id = 1;

        foreach (['', '<?php echo 1;', '<svg xmlns="http://www.w3.org/2000/svg"/>'] as $bytes) {
            try {
                (new AddGalleryImage)->handle($project, $bytes);
                $this->fail('The upload should be refused.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('file', $exception->errors());
            }
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_category_names_count_as_filled_only_with_text(): void
    {
        $this->assertFalse(SaveCategory::isFilled(null));
        $this->assertFalse(SaveCategory::isFilled(['name' => ' ', 'slug' => null]));
        $this->assertTrue(SaveCategory::isFilled(['slug' => 'web']));
    }
}
