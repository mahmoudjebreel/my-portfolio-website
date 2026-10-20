<?php

namespace App\Filament\Resources\Projects\Pages\Concerns;

use App\Enums\Locale;
use App\Filament\Support\SharedValidation;
use App\Http\Requests\Admin\ProjectRequest;
use App\Models\Project;
use App\Support\ProjectContent;

/**
 * Validates the project form with the ProjectRequest rules (the same as the admin API),
 * with the form labels in the messages.
 */
trait ValidatesProject
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function validateProject(?Project $project, array $data): array
    {
        $input = [
            'status' => $data['status'] ?? null,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'sort_order' => $data['sort_order'] ?? 0,
            'github_url' => self::blankToNull($data['github_url'] ?? null),
            'live_url' => self::blankToNull($data['live_url'] ?? null),
            'category_ids' => array_values(array_map('intval', $data['category_ids'] ?? [])),
            'technology_ids' => array_values(array_map('intval', $data['technology_ids'] ?? [])),
            'translations' => [],
        ];

        $attributes = [];
        foreach (Locale::cases() as $locale) {
            $content = [];
            foreach (ProjectContent::fields() as $field) {
                $content[$field] = self::blankToNull($data['translations'][$locale->value][$field] ?? null);
                $attributes["translations.{$locale->value}.{$field}"] = __("admin.projects.fields.{$field}");
            }
            $input['translations'][$locale->value] = $content;
        }
        $attributes['status'] = __('admin.projects.fields.status');
        $attributes['sort_order'] = __('admin.common.sort_order');
        $attributes['github_url'] = __('admin.projects.fields.github_url');
        $attributes['live_url'] = __('admin.projects.fields.live_url');

        return SharedValidation::validate(ProjectRequest::class, $project, $input, $attributes);
    }

    private static function blankToNull(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : (is_string($value) ? trim($value) : $value);
    }
}
