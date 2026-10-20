<?php

namespace App\Http\Requests\Admin;

use App\Enums\Locale;
use App\Support\SiteSettingsForm;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Save the editable site settings (A6). Only keys listed by SiteSettingsForm are accepted;
 * any other key is refused (array:… rules). An empty value clears the setting.
 */
class SiteSettingsRequest extends FormRequest
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
        return static::rulesFor(null, $this->input());
    }

    /**
     * Same rules for the API and the Filament settings page (App\Filament\Support\SharedValidation).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function rulesFor(mixed $record, array $input): array
    {
        $locales = implode(',', array_map(fn (Locale $l) => $l->value, Locale::cases()));

        $rules = [
            'texts' => ['required', 'array:'.implode(',', SiteSettingsForm::textKeys())],
            'values' => ['required', 'array:'.implode(',', array_keys(SiteSettingsForm::valueKeys()))],
        ];

        foreach (SiteSettingsForm::textKeys() as $key) {
            $rules["texts.{$key}"] = ['sometimes', 'array:'.$locales];
            foreach (Locale::cases() as $locale) {
                $rules["texts.{$key}.{$locale->value}"] = ['nullable', 'string', 'max:'.SiteSettingsForm::TEXT_LIMITS[$key]];
            }
        }

        foreach (SiteSettingsForm::valueKeys() as $key => $type) {
            $rules["values.{$key}"] = $type === 'email'
                ? ['nullable', 'string', 'max:'.SiteSettingsForm::EMAIL_MAX, 'email:rfc']
                : ['nullable', 'string', 'max:'.SiteSettingsForm::URL_MAX, 'url:http,https'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, callable>
     */
    public static function afterFor(mixed $record, array $input): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesFor(): array
    {
        return [];
    }
}
