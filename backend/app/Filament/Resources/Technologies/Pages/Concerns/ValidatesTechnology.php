<?php

namespace App\Filament\Resources\Technologies\Pages\Concerns;

use App\Enums\Locale;
use App\Filament\Support\SharedValidation;
use App\Http\Requests\Admin\TechnologyRequest;
use App\Models\Technology;

/**
 * Validates the technology form with the TechnologyRequest rules (the same as the admin API).
 * `is_visible` is passed only when creating, so an update can never change visibility.
 */
trait ValidatesTechnology
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function validateTechnology(?Technology $technology, array $data): array
    {
        $text = fn (mixed $value): ?string => is_string($value) && trim($value) !== '' ? trim($value) : null;

        $input = [
            'name' => $text($data['name'] ?? null),
            'slug' => $text($data['slug'] ?? null),
            'group' => $data['group'] ?? null,
            'icon' => $text($data['icon'] ?? null),
            'sort_order' => $data['sort_order'] ?? 0,
            'translations' => [],
        ];
        if ($technology === null) {
            $input['is_visible'] = (bool) ($data['is_visible'] ?? false);
        }

        $attributes = [
            'name' => __('admin.technologies.name'),
            'slug' => __('admin.technologies.slug'),
            'group' => __('admin.technologies.group'),
            'icon' => __('admin.technologies.icon'),
            'sort_order' => __('admin.common.sort_order'),
        ];
        foreach (Locale::cases() as $locale) {
            $input['translations'][$locale->value] = [
                'display_name' => $text($data['translations'][$locale->value]['display_name'] ?? null),
                'description' => $text($data['translations'][$locale->value]['description'] ?? null),
            ];
            $attributes["translations.{$locale->value}.display_name"] = __('admin.technologies.display_name');
            $attributes["translations.{$locale->value}.description"] = __('admin.technologies.description');
        }

        return SharedValidation::validate(TechnologyRequest::class, $technology, $input, $attributes, []);
    }
}
