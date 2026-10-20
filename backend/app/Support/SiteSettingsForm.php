<?php

namespace App\Support;

/**
 * Which site settings the administration may edit (A6), with their limits. Keys come only
 * from SiteSettingsRegistry: nothing new is invented here. Image settings stay read-only
 * (they hold storage paths), and nothing else in the database is ever read or written.
 */
final class SiteSettingsForm
{
    /** Maximum length of each translated text. */
    public const TEXT_LIMITS = [
        'display_name' => 100,
        'professional_title' => 150,
        'hero_eyebrow' => 150,
        'hero_headline' => 200,
        'hero_description' => 1000,
        'about_text' => 5000,
        'experience_text' => 5000,
        'footer_text' => 300,
        'seo_default_title' => 120,
        'seo_default_description' => 300,
    ];

    public const EMAIL_MAX = 254;

    public const URL_MAX = 500;

    /**
     * Translated text keys (one value per language), in registry order.
     *
     * @return list<string>
     */
    public static function textKeys(): array
    {
        return array_values(array_filter(
            array_keys(SiteSettingsRegistry::DEFINITIONS),
            fn (string $key) => SiteSettingsRegistry::DEFINITIONS[$key]['translatable'] && isset(self::TEXT_LIMITS[$key]),
        ));
    }

    /**
     * Single-value keys (email / URL), in registry order.
     *
     * @return array<string, 'email'|'url'>
     */
    public static function valueKeys(): array
    {
        $keys = [];
        foreach (SiteSettingsRegistry::DEFINITIONS as $key => $definition) {
            if (! $definition['translatable'] && in_array($definition['type'], ['email', 'url'], true)) {
                $keys[$key] = $definition['type'];
            }
        }

        return $keys;
    }

    /**
     * Image keys: shown (set / not set, preview), never edited here.
     *
     * @return list<string>
     */
    public static function imageKeys(): array
    {
        return array_keys(array_filter(SiteSettingsRegistry::DEFINITIONS, fn (array $definition) => $definition['type'] === 'image'));
    }

    public static function isPublic(string $key): bool
    {
        return SiteSettingsRegistry::DEFINITIONS[$key]['public'] ?? false;
    }
}
