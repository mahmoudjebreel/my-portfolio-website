<?php

namespace App\Http\Requests\Admin;

use App\Actions\Catalog\SaveCategory;
use App\Enums\Locale;
use App\Models\Category;
use App\Support\ProjectContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create / update a category from the administration (A5).
 *
 * Names per language (translations.en, translations.ar), each { name, slug }:
 * - at least one language is required; a language with any field filled must be complete
 *   (the table cannot store a partial translation);
 * - an existing language cannot be emptied here (nothing is deleted in this phase);
 * - name and slug are unique per language (same unique indexes as the table);
 * - an unchanged slug is accepted as stored, a new one must follow the slug format.
 */
class CategoryRequest extends FormRequest
{
    public const FIELDS = ['name', 'slug'];

    public function authorize(): bool
    {
        return true; // the "admin" middleware has already checked the session
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return static::rulesFor($this->category(), $this->input());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return static::messagesFor();
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return static::afterFor($this->category(), $this->input());
    }

    /**
     * Same rules for the API and the Filament panel (App\Filament\Support\SharedValidation).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function rulesFor(?Category $category, array $input): array
    {
        $rules = [
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'translations' => ['required', 'array:'.implode(',', array_map(fn (Locale $l) => $l->value, Locale::cases()))],
        ];

        foreach (Locale::cases() as $locale) {
            $prefix = "translations.{$locale->value}";
            $required = self::isFilled(data_get($input, $prefix)) || $category?->translation($locale) !== null ? 'required' : 'nullable';

            $slug = [$required, 'string', 'max:100'];
            if (data_get($input, "{$prefix}.slug") !== $category?->translation($locale)?->slug) {
                $slug[] = 'regex:'.ProjectContent::SLUG_PATTERN;
            }
            $slug[] = Rule::unique('category_translations', 'slug')->where('locale', $locale->value)->ignore($category?->getKey(), 'category_id');

            $rules[$prefix] = ['nullable', 'array:'.implode(',', self::FIELDS)];
            $rules["{$prefix}.name"] = [
                $required, 'string', 'max:80',
                Rule::unique('category_translations', 'name')->where('locale', $locale->value)->ignore($category?->getKey(), 'category_id'),
            ];
            $rules["{$prefix}.slug"] = $slug;
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function messagesFor(): array
    {
        return [
            'translations.*.name.unique' => __('This name is already used by another category in this language.'),
            'translations.*.slug.unique' => __('This slug is already used by another category in this language.'),
            'translations.*.slug.regex' => __('The slug may only contain lowercase Latin letters, numbers and single hyphens.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, callable>
     */
    public static function afterFor(?Category $category, array $input): array
    {
        return [
            function (Validator $validator) use ($input) {
                foreach (Locale::cases() as $locale) {
                    if (self::isFilled(data_get($input, "translations.{$locale->value}"))) {
                        return;
                    }
                }
                $validator->errors()->add('translations', __('Fill in the name in at least one language.'));
            },
        ];
    }

    /**
     * True when at least one field of a { name, slug } pair has a value.
     */
    public static function isFilled(mixed $content): bool
    {
        return SaveCategory::isFilled($content);
    }

    public function category(): ?Category
    {
        $category = $this->route('category');

        return $category instanceof Category ? $category : null;
    }
}
