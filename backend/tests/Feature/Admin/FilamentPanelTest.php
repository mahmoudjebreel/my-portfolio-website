<?php

namespace Tests\Feature\Admin;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Filament\Pages\Auth\Login;
use App\Models\User;
use App\Support\AdminLoginThrottle;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filament administration panel (F1) — WITHOUT any database access: array session and
 * cache (phpunit.xml), and every test fails if a query is attempted.
 */
class FilamentPanelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        DB::listen(fn ($query) => $this->fail('Unexpected database query: '.$query->sql));
    }

    public function test_the_panel_lives_at_admin_with_the_custom_login(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertSame('admin', $panel->getPath());
        $this->assertTrue($panel->isDefault());
        $this->assertSame(Login::class, $panel->getLoginRouteAction());
        $this->assertTrue($panel->hasDarkMode());
        $this->assertFalse($panel->hasRegistration());
        $this->assertFalse($panel->hasPasswordReset());
    }

    public function test_the_default_avatar_is_local_and_escaped(): void
    {
        $this->assertSame(InitialsAvatarProvider::class, Filament::getPanel('admin')->getDefaultAvatarProvider());

        $user = new User;
        $user->name = 'site <owner>';
        $url = (new InitialsAvatarProvider)->get($user);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $url);
        $svg = base64_decode(substr($url, strlen('data:image/svg+xml;base64,')));
        // Leading symbols are skipped, so markup in a name never reaches the SVG.
        $this->assertStringContainsString('>SO</text>', $svg);
        $this->assertStringNotContainsString('<owner', $svg);
        $this->assertStringNotContainsString('ui-avatars', $url);
    }

    public function test_guests_are_sent_to_the_sign_in_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_the_sign_in_page_has_no_remember_me_and_is_not_cached(): void
    {
        $response = $this->get('/admin/login')->assertOk();

        $response->assertSee('type="email"', false)->assertSee('type="password"', false);
        $response->assertDontSee('remember', false);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertSee('lang="en"', false)->assertSee('العربية');
    }

    public function test_the_language_switch_changes_the_panel_to_arabic_right_to_left(): void
    {
        $this->from('/admin/login')->get('/admin/locale/ar')->assertRedirect('/admin/login');

        $this->get('/admin/login')->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('lang="ar"', false)
            ->assertSee('English');

        // Without a referrer, the last panel page kept in the session is used.
        $this->get('/admin/locale/en')->assertRedirect('/admin/login');
        $this->get('/admin/locale/fr')->assertNotFound();
    }

    public function test_the_language_switch_never_redirects_outside_the_panel(): void
    {
        $this->from('https://evil.example/admin')->get('/admin/locale/ar')->assertRedirect('/admin');
        $this->from('/api/projects')->get('/admin/locale/ar')->assertRedirect('/admin');
    }

    public function test_only_administrators_can_access_the_panel(): void
    {
        $panel = Filament::getPanel('admin');
        $admin = new User;
        $admin->is_admin = true;
        $visitor = new User;
        $visitor->is_admin = false;

        $this->assertTrue($admin->canAccessPanel($panel));
        $this->assertFalse($visitor->canAccessPanel($panel));
        $this->assertFalse((new User)->canAccessPanel($panel));
        $this->assertNotContains('is_admin', (new User)->getFillable());
    }

    public function test_a_locked_out_email_is_refused_before_any_credential_check(): void
    {
        $key = AdminLoginThrottle::emailKey('owner@example.com', '127.0.0.1');
        foreach (range(1, AdminLoginThrottle::MAX_ATTEMPTS_PER_EMAIL) as $ignored) {
            RateLimiter::hit($key, 60);
        }

        Livewire::test(Login::class)
            ->fillForm(['email' => 'Owner@Example.com ', 'password' => 'whatever-password'])
            ->call('authenticate')
            ->assertNotified();

        $this->assertGuest();
    }

    public function test_ipv6_clients_share_one_limit_per_64_network(): void
    {
        $key = AdminLoginThrottle::addressKey('2001:db8:1:2::abcd');

        $this->assertSame($key, AdminLoginThrottle::addressKey('2001:db8:1:2:ffff::1'), 'same /64 network');
        $this->assertNotSame($key, AdminLoginThrottle::addressKey('2001:db8:1:3::1'));
        $this->assertNotSame(AdminLoginThrottle::addressKey('192.0.2.1'), AdminLoginThrottle::addressKey('192.0.2.2'));
    }

    public function test_too_many_attempts_from_one_address_are_refused_for_every_email(): void
    {
        // Livewire test requests come from 127.0.0.1.
        $key = AdminLoginThrottle::addressKey('127.0.0.1');
        foreach (range(1, AdminLoginThrottle::MAX_ATTEMPTS_PER_ADDRESS) as $ignored) {
            RateLimiter::hit($key, 60);
        }

        Livewire::test(Login::class)
            ->fillForm(['email' => 'someone-else@example.com', 'password' => 'whatever-password'])
            ->call('authenticate')
            ->assertNotified();

        $this->assertGuest();
    }

    public function test_the_public_api_routes_are_unchanged(): void
    {
        $baseline = json_decode((string) file_get_contents(base_path('../_backups/baseline/routes-public-api.json')), true);
        $current = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/') && ! str_starts_with($route->uri(), 'api/admin'))
            ->map(fn ($route) => $route->getName())
            ->sort()->values()->all();

        // The baseline (before the Filament move) plus the contact form, added afterwards.
        $expected = collect($baseline)->pluck('name')->push('api.contact-messages.store')->sort()->values()->all();

        $this->assertSame($expected, $current);
    }
}
