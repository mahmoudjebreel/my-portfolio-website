<?php

namespace App\Support;

use App\Enums\Locale;

/**
 * Rules for the per-language content of a project (project_translations).
 *
 * - A language is "filled" when any of its fields has a value.
 * - A language is "complete" when every required field has a value and the slug is valid.
 * - Publishing requires every language (English and Arabic) to be complete; a draft only
 *   needs one complete language, because the table cannot store a partial translation
 *   (title, slug, short_description and description are NOT NULL).
 */
final class ProjectContent
{
    public const REQUIRED = ['title', 'slug', 'short_description', 'description'];

    public const OPTIONAL = ['problem', 'solution', 'cover_image_alt'];

    /** Same slug rule as the public API routes. */
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * @return list<string>
     */
    public static function fields(): array
    {
        return [...self::REQUIRED, ...self::OPTIONAL];
    }

    /**
     * @param  array<string, mixed>|null  $content
     */
    public static function isFilled(?array $content): bool
    {
        foreach (self::fields() as $field) {
            if (self::hasValue($content[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $content
     */
    public static function isComplete(?array $content): bool
    {
        foreach (self::REQUIRED as $field) {
            if (! self::hasValue($content[$field] ?? null)) {
                return false;
            }
        }

        return preg_match(self::SLUG_PATTERN, (string) $content['slug']) === 1;
    }

    /**
     * Languages that are not complete, in the order of Locale::cases().
     *
     * @param  array<string, array<string, mixed>|null>  $contentByLocale
     * @return list<string>
     */
    public static function incompleteLocales(array $contentByLocale): array
    {
        $missing = [];
        foreach (Locale::cases() as $locale) {
            if (! self::isComplete($contentByLocale[$locale->value] ?? null)) {
                $missing[] = $locale->value;
            }
        }

        return $missing;
    }

    private static function hasValue(mixed $value): bool
    {
        return is_string($value) ? trim($value) !== '' : $value !== null;
    }
}
