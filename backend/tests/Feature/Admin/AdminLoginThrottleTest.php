<?php

namespace Tests\Feature\Admin;

use App\Support\AdminLoginThrottle;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Administration sign-in limiter keys and the login timebox, checked without any database
 * access (no RefreshDatabase, no queries): the keys are computed from the request only.
 */
class AdminLoginThrottleTest extends TestCase
{
    public function test_ipv4_keys_use_the_full_address_and_normalise_the_email(): void
    {
        $this->assertSame(
            'admin-login:'.hash('sha256', 'owner@example.com|203.0.113.10'),
            AdminLoginThrottle::emailKey(' Owner@Example.com ', '203.0.113.10'),
        );
        $this->assertSame('admin-login-ip:'.hash('sha256', '203.0.113.10'), AdminLoginThrottle::addressKey('203.0.113.10'));
        $this->assertNotSame(AdminLoginThrottle::addressKey('203.0.113.10'), AdminLoginThrottle::addressKey('203.0.113.11'));
    }

    public function test_ipv6_clients_of_one_64_share_every_key(): void
    {
        $this->assertSame(AdminLoginThrottle::addressKey('2001:db8:1:2::1'), AdminLoginThrottle::addressKey('2001:db8:1:2:ffff:ffff:ffff:ffff'));
        $this->assertSame(
            AdminLoginThrottle::emailKey('owner@example.com', '2001:db8:1:2::1'),
            AdminLoginThrottle::emailKey('owner@example.com', '2001:db8:1:2:dead:beef:0:1'),
        );
    }

    public function test_ipv6_clients_of_different_64s_and_different_emails_keep_separate_keys(): void
    {
        $this->assertNotSame(AdminLoginThrottle::addressKey('2001:db8:1:2::1'), AdminLoginThrottle::addressKey('2001:db8:1:3::1'));
        $this->assertNotSame(
            AdminLoginThrottle::emailKey('owner@example.com', '2001:db8:1:2::1'),
            AdminLoginThrottle::emailKey('other@example.com', '2001:db8:1:2::1'),
        );
    }

    public function test_the_limits_are_unchanged(): void
    {
        $this->assertSame(5, AdminLoginThrottle::MAX_ATTEMPTS_PER_EMAIL);
        $this->assertSame(20, AdminLoginThrottle::MAX_ATTEMPTS_PER_ADDRESS);
        $this->assertSame(60, AdminLoginThrottle::DECAY_SECONDS);
    }

    public function test_the_public_api_limiter_is_unchanged(): void
    {
        $limit = RateLimiter::limiter('public-api')(Request::create('/api/projects', server: ['REMOTE_ADDR' => '2001:db8:1:2::1']));
        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame('2001:db8:1:2::1', $limit->key);
    }

    public function test_failed_logins_are_padded_to_500_ms(): void
    {
        $this->assertSame(500000, config('auth.timebox_duration'));

        $guard = Auth::guard('web');
        $this->assertSame(500000, (new ReflectionProperty($guard, 'timeboxDuration'))->getValue($guard));
    }

    public function test_the_other_auth_settings_are_untouched(): void
    {
        $this->assertSame('web', config('auth.defaults.guard'));
        $this->assertSame('session', config('auth.guards.web.driver'));
        $this->assertSame('users', config('auth.guards.web.provider'));
        $this->assertSame(10800, (int) config('auth.password_timeout'));
    }
}
