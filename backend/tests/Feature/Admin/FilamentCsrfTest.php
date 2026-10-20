<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Admin\Concerns\StrictPreventRequestForgery;
use Tests\TestCase;

/**
 * CSRF protection of the Filament panel. The framework skips CSRF while PHPUnit is running,
 * so these tests swap in StrictPreventRequestForgery (the real middleware, without that skip).
 * No database access: the requests are refused before any query.
 */
class FilamentCsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        $this->app->bind(PreventRequestForgery::class, StrictPreventRequestForgery::class);
        DB::listen(fn ($query) => $this->fail('Unexpected database query: '.$query->sql));
    }

    public function test_the_web_group_and_the_panel_use_the_csrf_middleware_without_exclusions(): void
    {
        $this->assertContains(PreventRequestForgery::class, $this->app->make(HttpKernel::class)->getMiddlewareGroups()['web']);
        $this->assertContains(PreventRequestForgery::class, app('router')->getRoutes()->getByName('filament.admin.auth.logout')->gatherMiddleware());
        $this->assertSame([], $this->app->make(PreventRequestForgery::class)->getExcludedPaths());
    }

    public function test_signing_out_without_a_token_is_refused(): void
    {
        $user = new User;
        $user->id = 1;
        $user->is_admin = true;

        $this->actingAs($user, 'web')->post('/admin/logout')->assertStatus(419);
    }

    public function test_livewire_updates_without_a_token_are_refused(): void
    {
        $this->postJson(route('default-livewire.update'), ['components' => []])->assertStatus(419);
        $this->post(route('default-livewire.update'), [], ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);
    }

    public function test_the_removed_admin_api_no_longer_exists(): void
    {
        foreach (['/api/admin/me', '/api/admin/projects', '/api/admin/csrf-cookie'] as $uri) {
            $this->getJson($uri)->assertNotFound();
        }
        $this->postJson('/api/admin/login', ['email' => 'a@example.com', 'password' => 'x'])->assertNotFound();
    }
}
