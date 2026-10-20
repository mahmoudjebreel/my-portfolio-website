<?php

namespace App\Filament\Resources\Categories\Pages\Concerns;

use App\Enums\Locale;
use App\Filament\Support\SharedValidation;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;

/**
 * Validates the category form with the CategoryRequest rules (the same as the admin API).
 */
trait ValidatesCategory
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function validateCategory(?Category $category, array $data): array
    {
        $input = ['sort_order' => $data['sort_order'] ?? 0, 'translations' => []];
        $attributes = ['sort_order' => __('admin.common.sort_order')];

        foreach (Locale::cases() as $locale) {
            $names = [];
            foreach (CategoryRequest::FIELDS as $field) {
                $value = $data['translations'][$locale->value][$field] ?? null;
                $names[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
                $attributes["translations.{$locale->value}.{$field}"] = __("admin.categories.{$field}");
            }
            $input['translations'][$locale->value] = $names;
        }

        return SharedValidation::validate(CategoryRequest::class, $category, $input, $attributes);
    }
}
