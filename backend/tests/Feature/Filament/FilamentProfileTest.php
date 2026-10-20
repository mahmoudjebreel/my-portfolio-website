<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use App\Support\AdminAvatar;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "My profile" (F5): name, email, password and account picture of the signed-in admin only.
 * Test database only (TestCase guard + RefreshDatabase); fake private disk.
 */
class FilamentProfileTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct horse battery staple';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        Storage::fake('local');
        $this->admin = User::factory()->admin()->create(['name' => 'Site Owner', 'email' => 'owner@example.com', 'password' => self::PASSWORD]);
        $this->actingAs($this->admin, 'web');
        Filament::setCurrentPanel('admin');
    }

    public function test_the_profile_page_is_for_signed_in_administrators_only(): void
    {
        $this->get('/admin/profile')->assertOk()->assertSee('owner@example.com');

        $this->actingAs(User::factory()->create(), 'web');
        $this->get('/admin/profile')->assertForbidden();

        auth('web')->logout();
        $this->get('/admin/profile')->assertRedirect('/admin/login');
        $this->get('/admin/account/avatar')->assertRedirect('/admin/login');
    }

    public function test_the_name_changes_without_a_password_and_the_role_cannot(): void
    {
        $other = User::factory()->admin()->create(['name' => 'Other Admin']);

        Livewire::test(EditProfile::class)
            ->fillForm(['name' => 'New Name'])
            ->set('data.is_admin', false)
            ->set('data.id', $other->id)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('New Name', $this->admin->fresh()->name);
        $this->assertTrue($this->admin->fresh()->is_admin);
        $this->assertSame('Other Admin', $other->fresh()->name);
    }

    public function test_changing_the_email_needs_the_current_password_and_a_free_valid_address(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        Livewire::test(EditProfile::class)
            ->fillForm(['email' => 'new@example.com'])
            ->call('save')
            ->assertHasFormErrors(['currentPassword' => 'required']);

        Livewire::test(EditProfile::class)
            ->fillForm(['email' => 'new@example.com', 'currentPassword' => 'wrong password'])
            ->call('save')
            ->assertHasFormErrors(['currentPassword']);

        Livewire::test(EditProfile::class)
            ->fillForm(['email' => 'taken@example.com', 'currentPassword' => self::PASSWORD])
            ->call('save')
            ->assertHasFormErrors(['email']);

        Livewire::test(EditProfile::class)
            ->fillForm(['email' => 'not an email', 'currentPassword' => self::PASSWORD])
            ->call('save')
            ->assertHasFormErrors(['email']);

        $this->assertSame('owner@example.com', $this->admin->fresh()->email);

        Livewire::test(EditProfile::class)
            ->fillForm(['email' => 'new@example.com', 'currentPassword' => self::PASSWORD])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('new@example.com', $this->admin->fresh()->email);
    }

    public function test_the_new_password_follows_the_policy_and_is_hashed(): void
    {
        Livewire::test(EditProfile::class)
            ->fillForm(['password' => 'short-11ch!', 'passwordConfirmation' => 'short-11ch!', 'currentPassword' => self::PASSWORD])
            ->call('save')
            ->assertHasFormErrors(['password']);

        Livewire::test(EditProfile::class)
            ->fillForm(['password' => 'a much longer passphrase', 'passwordConfirmation' => 'something else entirely', 'currentPassword' => self::PASSWORD])
            ->call('save')
            ->assertHasFormErrors(['password']);

        Livewire::test(EditProfile::class)
            ->fillForm(['password' => 'a much longer passphrase', 'passwordConfirmation' => 'a much longer passphrase'])
            ->call('save')
            ->assertHasFormErrors(['currentPassword' => 'required']);

        $this->assertTrue(Hash::check(self::PASSWORD, $this->admin->fresh()->password));

        Livewire::test(EditProfile::class)
            ->fillForm(['password' => 'a much longer passphrase', 'passwordConfirmation' => 'a much longer passphrase', 'currentPassword' => self::PASSWORD])
            ->call('save')
            ->assertHasNoFormErrors();

        $stored = $this->admin->fresh()->password;
        $this->assertNotSame('a much longer passphrase', $stored);
        $this->assertTrue(Hash::check('a much longer passphrase', $stored));
        // Filament stores the new hash in this session, so AuthenticateSession keeps it signed in.
        $this->get('/admin')->assertOk();
        $this->assertAuthenticatedAs($this->admin, 'web');
    }

    public function test_other_sessions_end_after_the_password_changes(): void
    {
        // Another browser of the same account: AuthenticateSession remembers the old hash.
        $this->withSession(['password_hash_web' => $this->admin->password])->get('/admin')->assertOk();

        $this->admin->forceFill(['password' => Hash::make('a much longer passphrase')])->save();

        $this->get('/admin')->assertRedirect('/admin/login');
        $this->assertGuest('web');
    }

    public function test_an_account_picture_is_uploaded_shown_and_removed(): void
    {
        $other = User::factory()->admin()->create();
        Storage::disk('local')->put(AdminAvatar::path($other), 'other-avatar');

        Livewire::test(EditProfile::class)
            ->fillForm(['avatar' => UploadedFile::fake()->image('../../me.png', 1200, 900)])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = AdminAvatar::path($this->admin);
        $this->assertSame("avatars/{$this->admin->id}.webp", $path);
        Storage::disk('local')->assertExists($path);
        $bytes = Storage::disk('local')->get($path);
        $this->assertSame('WEBP', substr($bytes, 8, 4));
        $this->assertSame(512, getimagesizefromstring($bytes)[0], 'scaled down to 512 px');

        $url = $this->admin->fresh()->getFilamentAvatarUrl();
        $this->assertStringContainsString('/admin/account/avatar?v=', $url);
        $this->get('/admin/account/avatar')->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/admin')->assertSee('/admin/account/avatar?v=', false);

        Livewire::test(EditProfile::class)
            ->fillForm(['remove_avatar' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        Storage::disk('local')->assertMissing($path);
        Storage::disk('local')->assertExists(AdminAvatar::path($other));
        $this->assertNull($this->admin->fresh()->getFilamentAvatarUrl());
        $this->get('/admin/account/avatar')->assertNotFound();
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        Livewire::test(EditProfile::class)
            ->fillForm(['avatar' => UploadedFile::fake()->create('script.php', 4, 'application/x-php')])
            ->call('save')
            ->assertHasFormErrors(['avatar']);

        Livewire::test(EditProfile::class)
            ->fillForm(['avatar' => UploadedFile::fake()->image('huge.png')->size(6000)])
            ->call('save')
            ->assertHasFormErrors(['avatar']);

        $this->assertSame([], Storage::disk('local')->allFiles('avatars'));
    }
}
