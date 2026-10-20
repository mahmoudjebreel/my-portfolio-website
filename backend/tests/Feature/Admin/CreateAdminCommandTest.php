<?php

namespace Tests\Feature\Admin;

use App\Console\Commands\CreateAdminCommand;
use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Runs only against jebreel_portfolio_test (tests/TestCase.php guard + RefreshDatabase).
 */
class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD_QUESTION = 'Password (at least 12 characters, input hidden)';

    private const CONFIRM_QUESTION = 'Confirm password';

    private const PASSWORD = 'a long passphrase 2026';

    public function test_it_creates_an_administrator_without_printing_the_password(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Name', '  Site Owner ')
            ->expectsQuestion('Email address', ' Owner@Example.com ')
            ->expectsQuestion(self::PASSWORD_QUESTION, self::PASSWORD)
            ->expectsQuestion(self::CONFIRM_QUESTION, self::PASSWORD)
            ->expectsOutput('Administrator account created for owner@example.com.')
            ->doesntExpectOutputToContain(self::PASSWORD)
            ->assertSuccessful();

        $user = User::query()->sole();
        $this->assertSame('Site Owner', $user->name);
        $this->assertSame('owner@example.com', $user->email);
        $this->assertTrue($user->is_admin);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotSame(self::PASSWORD, $user->password);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertNull($user->remember_token);
    }

    public function test_the_created_admin_can_sign_in(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Name', 'Owner')
            ->expectsQuestion('Email address', 'owner@example.com')
            ->expectsQuestion(self::PASSWORD_QUESTION, self::PASSWORD)
            ->expectsQuestion(self::CONFIRM_QUESTION, self::PASSWORD)
            ->assertSuccessful();

        Filament::setCurrentPanel('admin');
        Livewire::test(Login::class)
            ->fillForm(['email' => 'owner@example.com', 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticated('web');
        $this->get('/admin')->assertOk();
    }

    public function test_a_password_shorter_than_12_characters_is_refused(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Name', 'Owner')
            ->expectsQuestion('Email address', 'owner@example.com')
            ->expectsQuestion(self::PASSWORD_QUESTION, '11-chars-pw')
            ->expectsQuestion(self::CONFIRM_QUESTION, '11-chars-pw')
            ->expectsOutputToContain('at least 12 characters')
            ->expectsOutput('No account was created.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_password_longer_than_bcrypt_can_use_is_refused(): void
    {
        $tooLong = str_repeat('a', CreateAdminCommand::MAX_PASSWORD_BYTES + 1);

        $this->artisan('admin:create')
            ->expectsQuestion('Name', 'Owner')
            ->expectsQuestion('Email address', 'owner@example.com')
            ->expectsQuestion(self::PASSWORD_QUESTION, $tooLong)
            ->expectsQuestion(self::CONFIRM_QUESTION, $tooLong)
            ->expectsOutputToContain('may not be longer than 72 bytes')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_mismatched_confirmation_is_refused(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Name', 'Owner')
            ->expectsQuestion('Email address', 'owner@example.com')
            ->expectsQuestion(self::PASSWORD_QUESTION, self::PASSWORD)
            ->expectsQuestion(self::CONFIRM_QUESTION, self::PASSWORD.'!')
            ->expectsOutput('The passwords do not match.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_an_invalid_email_or_empty_name_is_refused_before_asking_for_a_password(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Name', 'Owner')
            ->expectsQuestion('Email address', 'not-an-email')
            ->expectsOutput('No account was created.')
            ->assertFailed();

        $this->artisan('admin:create')
            ->expectsQuestion('Name', '   ')
            ->expectsQuestion('Email address', 'owner@example.com')
            ->expectsOutput('No account was created.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_an_existing_account_is_never_promoted_or_changed(): void
    {
        $existing = User::factory()->create(['email' => 'someone@example.com']);
        $hashBefore = $existing->password;

        // Refused before the password is even asked for.
        $this->artisan('admin:create')
            ->expectsQuestion('Name', 'Owner')
            ->expectsQuestion('Email address', 'SOMEONE@example.com')
            ->expectsOutput('A user with this email address already exists. This command never changes existing accounts.')
            ->assertFailed();

        $existing->refresh();
        $this->assertFalse($existing->is_admin);
        $this->assertSame($hashBefore, $existing->password);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_it_refuses_to_run_without_interaction(): void
    {
        $this->artisan('admin:create', ['--no-interaction' => true])
            ->expectsOutputToContain('interactive only')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_accepts_no_password_option_or_argument(): void
    {
        $definition = $this->app->make(CreateAdminCommand::class)->getDefinition();

        $this->assertSame([], $definition->getArguments());
        $this->assertFalse($definition->hasOption('password'));
    }
}
