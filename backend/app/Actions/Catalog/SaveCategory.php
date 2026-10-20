<?php

namespace App\Actions\Catalog;

use App\Enums\Locale;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a category and its names per language (admin API and Filament).
 * Callers validate first (CategoryRequest rules: one language at least, unique name and slug
 * per language). A language left empty is skipped, so an existing translation is never
 * removed or emptied. There is no delete.
 */
final class SaveCategory
{
    public const FIELDS = ['name', 'slug'];

    /**
     * @param  array{sort_order?: int, translations?: array<string, array{name?: string|null, slug?: string|null}|null>}  $data
     */
    public function handle(?Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            if ($category === null) {
                $category = Category::create(['sort_order' => (int) ($data['sort_order'] ?? 0)]);
                $creating = true;
            } else {
                $category->sort_order = array_key_exists('sort_order', $data) ? (int) $data['sort_order'] : $category->sort_order;
                $category->save();
                $creating = false;
            }

            foreach (Locale::cases() as $locale) {
                $names = $data['translations'][$locale->value] ?? null;
                if (! self::isFilled($names)) {
                    continue;
                }

                $category->translations()->updateOrCreate(
                    ['locale' => $locale->value],
                    ['name' => trim((string) ($names['name'] ?? '')), 'slug' => trim((string) ($names['slug'] ?? ''))],
                );
            }

            if (! $creating) {
                $category->touch(); // name-only changes also count as an update
            }

            return $category;
        });
    }

    /**
     * True when at least one field of a { name, slug } pair has text.
     */
    public static function isFilled(mixed $names): bool
    {
        if (! is_array($names)) {
            return false;
        }
        foreach (self::FIELDS as $field) {
            if (is_string($names[$field] ?? null) && trim($names[$field]) !== '') {
                return true;
            }
        }

        return false;
    }
}
