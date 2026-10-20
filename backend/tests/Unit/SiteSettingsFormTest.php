<?php

namespace Tests\Unit;

use App\Support\SiteSettingsForm;
use App\Support\SiteSettingsRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Editable site settings (A6) come only from the existing registry.
 */
class SiteSettingsFormTest extends TestCase
{
    public function test_every_editable_key_exists_in_the_registry(): void
    {
        $keys = [...SiteSettingsForm::textKeys(), ...array_keys(SiteSettingsForm::valueKeys()), ...SiteSettingsForm::imageKeys()];

        $this->assertSame([], array_diff($keys, array_keys(SiteSettingsRegistry::DEFINITIONS)));
        $this->assertEqualsCanonicalizing(array_keys(SiteSettingsRegistry::DEFINITIONS), $keys);
        $this->assertSame([], array_diff(array_keys(SiteSettingsForm::TEXT_LIMITS), array_keys(SiteSettingsRegistry::DEFINITIONS)));
    }

    public function test_keys_are_split_by_kind(): void
    {
        $this->assertSame(array_keys(SiteSettingsForm::TEXT_LIMITS), SiteSettingsForm::textKeys());
        $this->assertSame(
            ['contact_email' => 'email', 'github_url' => 'url', 'linkedin_url' => 'url', 'notification_email' => 'email'],
            SiteSettingsForm::valueKeys(),
        );
        $this->assertSame(['profile_image_path', 'og_default_image_path'], SiteSettingsForm::imageKeys());
        $this->assertFalse(SiteSettingsForm::isPublic('notification_email'));
        $this->assertTrue(SiteSettingsForm::isPublic('contact_email'));
    }
}
