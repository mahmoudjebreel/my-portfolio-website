<?php

namespace App\Http\Requests\Admin;

use App\Enums\Locale;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Support\ProjectContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create / update a project from the administration.
 *
 * Content per language (translations.en, translations.ar):
 * - publishing requires both languages complete;
 * - a draft needs at least one complete language; a language with any field filled must be
 *   complete (the table cannot store a partial translation);
 * - an existing translation cannot be emptied here (nothing is deleted in this phase).
 *
 * Only the fields listed in rules() are used: cover_image_path, published_at and other
 * columns are never taken from the request.
 *
 * The rules are static (rulesFor / afterFor / messagesFor) so the Filament panel validates
 * with exactly the same rules as the API (App\Filament\Support\SharedValidation).
 */
class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the "admin" middleware has already checked the session
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return static::rulesFor($this->project(), $this->input());
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return static::afterFor($this->project(), $this->input());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return static::messagesFor();
    }

    public function project(): ?Project
    {
        $project = $this->route('project');

        return $project instanceof Project ? $project : null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function rulesFor(?Project $project, array $input): array
    {
        $url = ['nullable', 'string', 'max:500', 'url:http,https'];

        $rules = [
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'github_url' => $url,
            'live_url' => $url,
            'category_ids' => ['sometimes', 'array', 'max:20'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('categories', 'id')],
            'technology_ids' => ['sometimes', 'array', 'max:50'],
            'technology_ids.*' => ['integer', 'distinct', Rule::exists('technologies', 'id')],
            'translations' => ['required', 'array:'.implode(',', array_map(fn (Locale $l) => $l->value, Locale::cases()))],
        ];

        foreach (Locale::cases() as $locale) {
            $prefix = "translations.{$locale->value}";
            $required = self::contentRequired($project, $input, $locale->value) ? 'required' : 'nullable';

            $rules[$prefix] = ['nullable', 'array:'.implode(',', ProjectContent::fields())];
            $rules["{$prefix}.title"] = [$required, 'string', 'max:150'];
            $rules["{$prefix}.slug"] = [
                $required, 'string', 'max:160', 'regex:'.ProjectContent::SLUG_PATTERN,
                Rule::unique('project_translations', 'slug')
                    ->where('locale', $locale->value)
                    ->ignore($project?->getKey(), 'project_id'),
            ];
            $rules["{$prefix}.short_description"] = [$required, 'string', 'max:300'];
            $rules["{$prefix}.description"] = [$required, 'string', 'max:20000'];
            $rules["{$prefix}.problem"] = ['nullable', 'string', 'max:20000'];
            $rules["{$prefix}.solution"] = ['nullable', 'string', 'max:20000'];
            $rules["{$prefix}.cover_image_alt"] = ['nullable', 'string', 'max:200'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, \Closure>
     */
    public static function afterFor(?Project $project, array $input): array
    {
        return [
            function (Validator $validator) use ($input) {
                $filled = array_filter(Locale::cases(), fn (Locale $l) => ProjectContent::isFilled(self::content($input, $l->value)));
                if ($filled === []) {
                    $validator->errors()->add('translations', __('Fill in the content in at least one language.'));
                }
                if (self::publishing($input) && ProjectContent::incompleteLocales(self::contentByLocale($input)) !== []) {
                    $validator->errors()->add('status', __('To publish, complete the English and Arabic content.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesFor(): array
    {
        return [
            'translations.*.slug.regex' => __('Use lowercase Latin letters, numbers and single hyphens.'),
            'translations.*.slug.unique' => __('This slug is already used by another project in this language.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function publishing(array $input): bool
    {
        $status = $input['status'] ?? null;

        return ($status instanceof ProjectStatus ? $status->value : $status) === ProjectStatus::Published->value;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|null
     */
    private static function content(array $input, string $locale): ?array
    {
        $content = data_get($input, "translations.{$locale}");

        return is_array($content) ? $content : null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<string, mixed>|null>
     */
    private static function contentByLocale(array $input): array
    {
        $byLocale = [];
        foreach (Locale::cases() as $locale) {
            $byLocale[$locale->value] = self::content($input, $locale->value);
        }

        return $byLocale;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function contentRequired(?Project $project, array $input, string $locale): bool
    {
        return self::publishing($input)
            || ProjectContent::isFilled(self::content($input, $locale))
            || ($project?->translations()->where('locale', $locale)->exists() ?? false);
    }
}
