<?php

namespace Tests\Feature\Api;

use App\Enums\Locale;
use App\Models\SiteSetting;
use App\Support\SiteSettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private const STRUCTURE = [
        'data' => [
            'locale', 'display_name', 'professional_title', 'hero_eyebrow', 'hero_headline',
            'hero_description', 'about_text', 'experience_text', 'footer_text',
            'seo' => ['title', 'description', 'image_url'],
            'contact' => ['email', 'github_url', 'linkedin_url'],
            'profile_image',
        ],
    ];

    private function plain(string $key, mixed $value): SiteSetting
    {
        return SiteSetting::create(['key' => $key, 'value' => $value]);
    }

    /**
     * @param  array<string, string>  $values  locale => value
     */
    private function translated(string $key, array $values): SiteSetting
    {
        $setting = SiteSetting::create(['key' => $key, 'value' => null]);
        foreach ($values as $locale => $value) {
            $setting->translations()->create(['locale' => $locale, 'value' => $value]);
        }

        return $setting;
    }

    public function test_empty_database_returns_every_public_key_as_null(): void
    {
        $response = $this->getJson('/api/site-settings')->assertOk()->assertExactJsonStructure(self::STRUCTURE);

        $data = $response->json('data');
        $this->assertSame('en', $data['locale'], 'defaults to English');
        $this->assertNull($data['hero_headline']);
        $this->assertNull($data['profile_image']);
        $this->assertSame(['email' => null, 'github_url' => null, 'linkedin_url' => null], $data['contact']);
    }

    public function test_private_and_unknown_settings_never_leak(): void
    {
        $this->plain('notification_email', 'private-inbox@example.com');
        $this->plain('internal_api_secret', 'super-secret-value');
        $this->translated('internal_note', ['en' => 'hidden internal note']);
        $this->translated('hero_headline', ['en' => 'Public headline']);

        foreach (['en', 'ar'] as $locale) {
            $this->getJson("/api/site-settings?locale={$locale}")
                ->assertOk()
                ->assertExactJsonStructure(self::STRUCTURE)
                ->assertDontSee('private-inbox@example.com')
                ->assertDontSee('notification_email')
                ->assertDontSee('super-secret-value')
                ->assertDontSee('hidden internal note');
        }
    }

    public function test_translated_values_follow_the_locale_without_fallback(): void
    {
        $this->translated('hero_headline', ['en' => 'Building Digital Experiences', 'ar' => 'نص عربي للاختبار']);
        $this->translated('about_text', ['en' => 'English only text']);

        $this->getJson('/api/site-settings?locale=en')
            ->assertJsonPath('data.hero_headline', 'Building Digital Experiences')
            ->assertJsonPath('data.about_text', 'English only text');

        $this->getJson('/api/site-settings?locale=ar')
            ->assertJsonPath('data.locale', 'ar')
            ->assertJsonPath('data.hero_headline', 'نص عربي للاختبار')
            ->assertJsonPath('data.about_text', null)
            ->assertDontSee('English only text');
    }

    public function test_contact_values_are_validated_before_being_exposed(): void
    {
        $this->plain('contact_email', 'not-an-email');
        $this->plain('github_url', 'javascript:alert(1)');
        $this->plain('linkedin_url', 'https://www.linkedin.com/in/someone');

        $this->getJson('/api/site-settings')
            ->assertJsonPath('data.contact.email', null)
            ->assertJsonPath('data.contact.github_url', null)
            ->assertJsonPath('data.contact.linkedin_url', 'https://www.linkedin.com/in/someone');
    }

    public function test_image_urls_are_returned_only_for_existing_public_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profile/me.jpg', 'image-bytes');

        $this->plain('profile_image_path', 'profile/me.jpg');
        $this->plain('og_default_image_path', 'og/missing.jpg');
        $this->translated('display_name', ['en' => 'Display Name']);

        $this->getJson('/api/site-settings?locale=en')
            ->assertJsonPath('data.profile_image', ['url' => '/storage/profile/me.jpg', 'alt' => 'Display Name'])
            ->assertJsonPath('data.seo.image_url', null);
    }

    public function test_unsafe_image_paths_are_rejected(): void
    {
        Storage::fake('public');

        foreach (['../.env', '/etc/passwd', 'C:/Windows/win.ini'] as $path) {
            SiteSetting::query()->where('key', 'profile_image_path')->delete();
            $this->plain('profile_image_path', $path);

            $this->getJson('/api/site-settings')->assertJsonPath('data.profile_image', null);
        }
    }

    public function test_the_registry_marks_notification_email_as_private(): void
    {
        $this->assertNotContains('notification_email', SiteSettingsRegistry::publicKeys());
        $this->assertSame(Locale::En, Locale::from('en'));
    }
}
