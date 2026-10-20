<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use App\Support\AdminLoginThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filament sign-in with real accounts (F1). Test database only (TestCase guard +
 * RefreshDatabase).
 */
class FilamentLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct horse battery staple';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
    }

    public function test_an_administrator_signs_in_and_reaches_the_dashboard(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'owner@example.com', 'password' => self::PASSWORD]);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'owner@example.com', 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin, 'web');
        $this->get('/admin')->assertOk();
    }

    public function test_a_regular_account_gets_the_same_failure_as_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'visitor@example.com', 'password' => self::PASSWORD]);
        User::factory()->admin()->create(['email' => 'owner@example.com', 'password' => self::PASSWORD]);

        $regular = Livewire::test(Login::class)
            ->fillForm(['email' => 'visitor@example.com', 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
        $wrong = Livewire::test(Login::class)
            ->fillForm(['email' => 'owner@example.com', 'password' => 'not the password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
        Livewire::test(Login::class)
            ->fillForm(['email' => 'nobody@example.com', 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertSame($regular->errors()->get('data.email'), $wrong->errors()->get('data.email'));
        $this->assertGuest('web');
    }

    public function test_a_signed_in_regular_account_cannot_open_the_panel(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->get('/admin')->assertForbidden();
    }

    public function test_failed_attempts_are_counted_per_email_and_cleared_after_success(): void
    {
        User::factory()->admin()->create(['email' => 'owner@example.com', 'password' => self::PASSWORD]);
        $key = AdminLoginThrottle::emailKey('owner@example.com', '127.0.0.1');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'owner@example.com', 'password' => 'wrong password'])
            ->call('authenticate');
        $this->assertSame(1, RateLimiter::attempts($key));

        Livewire::test(Login::class)
            ->fillForm(['email' => 'owner@example.com', 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertSame(0, RateLimiter::attempts($key));
    }

    public function test_signing_out_ends_the_session(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'web');

        $this->post('/admin/logout')->assertRedirect();
        $this->assertGuest('web');
        $this->get('/admin')->assertRedirect('/admin/login');
    }
}
