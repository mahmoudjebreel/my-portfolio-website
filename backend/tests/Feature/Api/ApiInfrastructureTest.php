<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function endpoints(): array
    {
        return [
            'site-settings' => ['/api/site-settings'],
            'technologies' => ['/api/technologies'],
            'categories' => ['/api/categories'],
            'projects' => ['/api/projects'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_endpoint_responds_with_json_for_both_locales(string $uri): void
    {
        foreach (['en', 'ar'] as $locale) {
            $this->getJson("{$uri}?locale={$locale}")->assertOk()->assertHeader('Content-Type', 'application/json');
        }
    }

    #[DataProvider('endpoints')]
    public function test_unsupported_locale_is_rejected(string $uri): void
    {
        $this->getJson("{$uri}?locale=fr")->assertStatus(422)->assertJsonValidationErrors('locale');
        $this->getJson("{$uri}?locale[]=en")->assertStatus(422)->assertJsonValidationErrors('locale');
    }

    #[DataProvider('endpoints')]
    public function test_only_get_is_allowed(string $uri): void
    {
        $this->postJson($uri)->assertStatus(405);
        $this->putJson($uri)->assertStatus(405);
        $this->patchJson($uri)->assertStatus(405);
        $this->deleteJson($uri)->assertStatus(405);
    }

    #[DataProvider('endpoints')]
    public function test_api_is_stateless_and_sets_no_cookies(string $uri): void
    {
        $this->getJson($uri)->assertOk()->assertHeaderMissing('Set-Cookie');
    }

    public function test_unknown_api_route_returns_a_generic_json_404(): void
    {
        $this->getJson('/api/does-not-exist')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    }

    public function test_cors_is_closed_by_default(): void
    {
        $this->assertSame([], config('cors.allowed_origins'));
        $this->assertNotContains('*', config('cors.allowed_methods'));
    }

    public function test_unlisted_origin_gets_no_cors_header(): void
    {
        $this->withHeaders(['Origin' => 'https://evil.example'])
            ->getJson('/api/technologies')
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');

        // Preflight from the same origin is not granted either.
        $this->call('OPTIONS', '/api/technologies', server: [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ])->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_explicitly_allowed_origin_gets_the_cors_header(): void
    {
        config(['cors.allowed_origins' => ['https://allowed.example']]);

        $this->withHeaders(['Origin' => 'https://allowed.example'])
            ->getJson('/api/technologies')
            ->assertHeader('Access-Control-Allow-Origin', 'https://allowed.example');

        // With a single allowed origin the CORS library always answers with that fixed
        // origin; a foreign origin is never echoed back, so the browser denies it.
        $foreign = $this->withHeaders(['Origin' => 'https://evil.example'])->getJson('/api/technologies');
        $this->assertNotSame('https://evil.example', $foreign->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $foreign->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_with_several_allowed_origins_only_a_matching_origin_is_echoed(): void
    {
        config(['cors.allowed_origins' => ['https://one.example', 'https://two.example']]);

        $this->withHeaders(['Origin' => 'https://two.example'])
            ->getJson('/api/technologies')
            ->assertHeader('Access-Control-Allow-Origin', 'https://two.example');

        $this->withHeaders(['Origin' => 'https://evil.example'])
            ->getJson('/api/technologies')
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_public_api_is_rate_limited_once_at_sixty_requests_per_minute(): void
    {
        for ($i = 1; $i <= 60; $i++) {
            $response = $this->getJson('/api/technologies')->assertOk();
        }

        // A single limiter: the remaining count reaches exactly zero after 60 requests.
        $response->assertHeader('X-RateLimit-Limit', '60')->assertHeader('X-RateLimit-Remaining', '0');

        $this->getJson('/api/technologies')->assertStatus(429);
    }

    public function test_private_local_disk_is_not_served_over_http(): void
    {
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->assertFalse(Route::has('storage.local'));
        $this->assertFalse(Route::has('storage.local.upload'));
    }

    public function test_only_read_only_routes_and_the_contact_form_exist_under_api(): void
    {
        $apiRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'));

        // The API is the public API only: the administration is the Filament panel (/admin),
        // and the former admin API (/api/admin) no longer exists. The single write is the
        // contact form, which can only create a message.
        $this->assertNotEmpty($apiRoutes);

        foreach ($apiRoutes as $route) {
            $expected = $route->uri() === 'api/contact-messages' ? ['POST'] : ['GET', 'HEAD'];
            $this->assertEqualsCanonicalizing($expected, $route->methods(), $route->uri());
            $this->assertFalse(str_starts_with($route->uri(), 'api/admin'), $route->uri());
        }
    }
}
