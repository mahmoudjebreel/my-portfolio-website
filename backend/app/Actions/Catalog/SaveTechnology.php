<?php

namespace App\Actions\Catalog;

use App\Enums\Locale;
use App\Enums\TechnologyGroup;
use App\Models\Technology;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a technology (admin API and Filament). Callers validate first
 * (TechnologyRequest rules). Business rules kept here:
 *
 * - visibility on the public site is chosen only when creating (hidden by default); an update
 *   never changes it, so editing never makes a hidden technology public;
 * - an empty icon is stored as null;
 * - translated display name / description are optional: an existing translation is updated
 *   (cleared fields become null, the row is kept), a new one is created only when filled.
 */
final class SaveTechnology
{
    public const TRANSLATION_FIELDS = ['display_name', 'description'];

    /**
     * @param  array{
     *     name: string,
     *     slug: string,
     *     group: string|TechnologyGroup,
     *     icon?: string|null,
     *     sort_order?: int,
     *     is_visible?: bool,
     *     translations?: array<string, array{display_name?: string|null, description?: string|null}|null>,
     * }  $data
     */
    public function handle(?Technology $technology, array $data): Technology
    {
        return DB::transaction(function () use ($technology, $data) {
            $creating = $technology === null;
            $technology ??= new Technology;

            $icon = $data['icon'] ?? null;
            $technology->name = trim($data['name']);
            $technology->slug = trim($data['slug']);
            $technology->group = $data['group'] instanceof TechnologyGroup ? $data['group'] : TechnologyGroup::from($data['group']);
            $technology->icon = is_string($icon) && trim($icon) !== '' ? trim($icon) : null;
            $technology->sort_order = array_key_exists('sort_order', $data) ? (int) $data['sort_order'] : (int) ($technology->sort_order ?? 0);

            if ($creating) {
                $technology->is_visible = (bool) ($data['is_visible'] ?? false);
            }

            $technology->save();

            if (array_key_exists('translations', $data)) {
                $this->saveTranslations($technology, $data['translations'] ?? []);
            }

            if (! $creating) {
                $technology->touch(); // translation-only changes also count as an update
            }

            return $technology;
        });
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    private function saveTranslations(Technology $technology, array $translations): void
    {
        foreach (Locale::cases() as $locale) {
            $content = $translations[$locale->value] ?? null;
            $values = [];
            foreach (self::TRANSLATION_FIELDS as $field) {
                $value = is_array($content) ? ($content[$field] ?? null) : null;
                $values[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
            }

            $existing = $technology->translations()->where('locale', $locale->value)->first();
            if ($existing !== null) {
                $existing->update($values);
            } elseif (array_filter($values) !== []) {
                $technology->translations()->create(['locale' => $locale->value] + $values);
            }
        }
    }
}
