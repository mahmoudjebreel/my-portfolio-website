<?php

namespace App\Support;

use App\Enums\Locale;
use App\Models\SiteSetting;

/**
 * The single source of truth for site setting keys.
 *
 * Only keys listed here with `public => true` can ever be read by the public API:
 * the query itself is restricted to those keys, so private settings (for example
 * `notification_email`) or unknown rows stored in the database never leak.
 */
final class SiteSettingsRegistry
{
    /**
     * @var array<string, array{public: bool, translatable: bool, type: 'text'|'email'|'url'|'image'}>
     */
    public const DEFINITIONS = [
        'display_name' => ['public' => true, 'translatable' => true, 'type' => 'text'],
        'professional_title' => ['public' => true, 'translatable' => true, 'type' => 'text'],
        'hero_eyebrow' => ['public' => true, 'translatable' => true, 'type' => 'text'],
        'hero_headline' => ['public' => true, 'translatable' => true, 'type' => 'text'],
        'hero_description' => ['public' => true, 'translatable' => true, 'type' => 'text'],
        'about_text' => ['public' => true, 'translatable' => true, 'type' => 'text'],
        'experience_text' => ['public' => true, 'translatable' => true, 'type' => 'text'],
        'footer_text' => ['public' => true, 'translatable' => true, 'type' => 'text'],
        'seo_default_title' => ['public' => true, 'translatable' => true, 'type' => 'text'],
        'seo_default_description' => ['public' => true, 'translatable' => true, 'type' => 'text'],
        'contact_email' => ['public' => true, 'translatable' => false, 'type' => 'email'],
        'github_url' => ['public' => true, 'translatable' => false, 'type' => 'url'],
        'linkedin_url' => ['public' => true, 'translatable' => false, 'type' => 'url'],
        'profile_image_path' => ['public' => true, 'translatable' => false, 'type' => 'image'],
        'og_default_image_path' => ['public' => true, 'translatable' => false, 'type' => 'image'],
        'notification_email' => ['public' => false, 'translatable' => false, 'type' => 'email'],
    ];

    /**
     * @return list<string>
     */
    public static function publicKeys(): array
    {
        return array_keys(array_filter(self::DEFINITIONS, fn (array $definition) => $definition['public']));
    }

    /**
     * Every public key mapped to its sanitized value for the locale, or null when
     * missing/invalid. Translated values never fall back to another language.
     *
     * @return array<string, string|null>
     */
    public static function publicValues(Locale $locale): array
    {
        $keys = self::publicKeys();

        $settings = SiteSetting::query()
            ->whereIn('key', $keys)
            ->with(['translations' => fn ($query) => $query->where('locale', $locale->value)])
            ->get()
            ->keyBy('key');

        $values = [];

        foreach ($keys as $key) {
            $definition = self::DEFINITIONS[$key];
            $setting = $settings->get($key);

            $raw = match (true) {
                $setting === null => null,
                $definition['translatable'] => $setting->translations->first()?->value,
                default => $setting->value,
            };

            $values[$key] = self::sanitize($raw, $definition['type']);
        }

        return $values;
    }

    private static function sanitize(mixed $value, string $type): ?string
    {
        if ($type === 'image') {
            return PublicFile::url($value);
        }

        if (! is_string($value) || ($value = trim($value)) === '') {
            return null;
        }

        return match ($type) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? $value : null,
            'url' => self::isHttpUrl($value) ? $value : null,
            default => $value,
        };
    }

    private static function isHttpUrl(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
