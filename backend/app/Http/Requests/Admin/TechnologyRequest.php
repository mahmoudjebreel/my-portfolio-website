<?php

namespace App\Http\Requests\Admin;

use App\Enums\Locale;
use App\Enums\TechnologyGroup;
use App\Models\Technology;
use App\Support\ProjectContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create / update a technology from the administration (A5).
 *
 * - `name` is the canonical (brand) name; `display_name` / `description` per language are optional.
 * - `icon` is optional: a short icon key (letters, numbers, . _ : / -).
 * - An unchanged slug or icon is accepted as stored, even if it predates these rules.
 * - Visibility on the public site (`is_visible`) can only be chosen when creating (hidden by
 *   default). Updates never read it, so editing never makes a hidden technology public.
 */
class TechnologyRequest extends FormRequest
{
    public const ICON_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:\/-]*$/D';

    public const TRANSLATION_FIELDS = ['display_name', 'description'];

    public function authorize(): bool
    {
        return true; // the "admin" middleware has already checked the session
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return static::rulesFor($this->technology(), $this->input());
    }

    /**
     * Same rules for the API and the Filament panel (App\Filament\Support\SharedValidation).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function rulesFor(?Technology $technology, array $input): array
    {
        $slug = ['required', 'string', 'max:80'];
        if (($input['slug'] ?? null) !== $technology?->slug) {
            $slug[] = 'regex:'.ProjectContent::SLUG_PATTERN;
        }
        $slug[] = Rule::unique('technologies', 'slug')->ignore($technology?->getKey());

        $icon = ['nullable', 'string', 'max:60'];
        if (($input['icon'] ?? null) !== $technology?->icon) {
            $icon[] = 'regex:'.self::ICON_PATTERN;
        }

        $rules = [
            'name' => ['required', 'string', 'max:60', Rule::unique('technologies', 'name')->ignore($technology?->getKey())],
            'slug' => $slug,
            'group' => ['required', Rule::enum(TechnologyGroup::class)],
            'icon' => $icon,
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'translations' => ['sometimes', 'array:'.implode(',', array_map(fn (Locale $l) => $l->value, Locale::cases()))],
        ];

        if ($technology === null) {
            $rules['is_visible'] = ['sometimes', 'boolean'];
        }

        foreach (Locale::cases() as $locale) {
            $prefix = "translations.{$locale->value}";
            $rules[$prefix] = ['nullable', 'array:'.implode(',', self::TRANSLATION_FIELDS)];
            $rules["{$prefix}.display_name"] = ['nullable', 'string', 'max:60'];
            $rules["{$prefix}.description"] = ['nullable', 'string', 'max:300'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, callable>
     */
    public static function afterFor(?Technology $technology, array $input): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return static::messagesFor();
    }

    /**
     * @return array<string, string>
     */
    public static function messagesFor(): array
    {
        return [
            'name.unique' => __('This name is already used by another technology.'),
            'slug.unique' => __('This slug is already used by another technology.'),
            'slug.regex' => __('The slug may only contain lowercase Latin letters, numbers and single hyphens.'),
            'icon.regex' => __('The icon may only contain letters, numbers and . _ : / -'),
        ];
    }

    public function technology(): ?Technology
    {
        $technology = $this->route('technology');

        return $technology instanceof Technology ? $technology : null;
    }
}
