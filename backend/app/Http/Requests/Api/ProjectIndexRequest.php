<?php

namespace App\Http\Requests\Api;

use App\Enums\Locale;
use Illuminate\Validation\Rule;

/**
 * Query parameters for the public project list. Filters that do not exist
 * (or are not visible in the requested locale) are rejected with 422 instead
 * of silently returning an empty list.
 */
class ProjectIndexRequest extends LocaleRequest
{
    private const SLUG = 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Filters are checked against the requested locale (or the default when it is invalid,
        // in which case the locale error is reported anyway).
        $requested = $this->query('locale', Locale::En->value);
        $locale = (is_string($requested) ? Locale::tryFrom($requested) : null) ?? Locale::En;

        return parent::rules() + [
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'between:1,24'],
            'featured' => ['sometimes', 'boolean'],
            'category' => [
                'sometimes', 'string', 'max:100', self::SLUG,
                Rule::exists('category_translations', 'slug')->where('locale', $locale->value),
            ],
            'technology' => [
                'sometimes', 'string', 'max:80', self::SLUG,
                Rule::exists('technologies', 'slug')->where('is_visible', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.exists' => 'The selected category does not exist in the requested language.',
            'technology.exists' => 'The selected technology does not exist.',
            'category.regex' => 'The category must be a valid slug.',
            'technology.regex' => 'The technology must be a valid slug.',
        ];
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 9);
    }

    public function onlyFeatured(): bool
    {
        return $this->boolean('featured');
    }
}
