<?php

namespace App\Actions\Settings;

use App\Enums\Locale;
use App\Models\SiteSetting;
use App\Support\PublicFile;
use App\Support\SiteSettingsForm;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads and saves the editable site settings (admin API and Filament). Only keys listed by
 * SiteSettingsForm (from SiteSettingsRegistry) are ever read or written; anything else in the
 * input is ignored. Nothing is deleted: clearing a value stores an empty value, which the
 * public API already treats as "not set", and no row is created for a key left empty.
 * Image settings are read-only here and are shown without their storage paths.
 */
final class SaveSiteSettings
{
    /**
     * @param  array<string, array<string, string|null>>  $texts  key => [locale => text]; missing keys are left as they are
     * @param  array<string, string|null>  $values  key => email / URL; missing keys are left as they are
     */
    public function handle(array $texts, array $values): void
    {
        DB::transaction(function () use ($texts, $values) {
            $settings = self::settings();

            foreach (SiteSettingsForm::textKeys() as $key) {
                if (! array_key_exists($key, $texts)) {
                    continue;
                }
                $setting = $settings->get($key);
                foreach (Locale::cases() as $locale) {
                    $value = trim((string) ($texts[$key][$locale->value] ?? ''));
                    $translation = $setting?->translations->first(fn ($t) => $t->locale === $locale);

                    if ($translation !== null) {
                        if ($translation->value !== $value) {
                            $translation->update(['value' => $value]);
                        }
                    } elseif ($value !== '') {
                        $setting ??= SiteSetting::create(['key' => $key, 'value' => null]);
                        $setting->translations()->create(['locale' => $locale->value, 'value' => $value]);
                    }
                }
            }

            foreach (array_keys(SiteSettingsForm::valueKeys()) as $key) {
                if (! array_key_exists($key, $values)) {
                    continue;
                }
                $value = trim((string) $values[$key]);
                $value = $value === '' ? null : $value;
                $setting = $settings->get($key);

                if ($setting !== null) {
                    if ($setting->value !== $value) {
                        $setting->update(['value' => $value]);
                    }
                } elseif ($value !== null) {
                    SiteSetting::create(['key' => $key, 'value' => $value]);
                }
            }
        });
    }

    /**
     * Current values of every editable key, the image settings (set / public URL only), the
     * limits, and which keys are private (never shown on the website).
     *
     * @return array{texts: array<string, array<string, string|null>>, values: array<string, string|null>, images: array<string, array{set: bool, url: string|null}>, limits: array<string, mixed>, private_keys: list<string>}
     */
    public static function snapshot(): array
    {
        $settings = self::settings();

        $texts = [];
        foreach (SiteSettingsForm::textKeys() as $key) {
            foreach (Locale::cases() as $locale) {
                $texts[$key][$locale->value] = $settings->get($key)?->translations->first(fn ($t) => $t->locale === $locale)?->value;
            }
        }

        $values = [];
        foreach (array_keys(SiteSettingsForm::valueKeys()) as $key) {
            $value = $settings->get($key)?->value;
            $values[$key] = is_string($value) ? $value : null;
        }

        $images = [];
        foreach (SiteSettingsForm::imageKeys() as $key) {
            $path = $settings->get($key)?->value;
            $images[$key] = [
                'set' => is_string($path) && trim($path) !== '',
                'url' => PublicFile::url($path),
            ];
        }

        $private = array_values(array_filter(
            [...SiteSettingsForm::textKeys(), ...array_keys(SiteSettingsForm::valueKeys())],
            fn (string $key) => ! SiteSettingsForm::isPublic($key),
        ));

        return [
            'texts' => $texts,
            'values' => $values,
            'images' => $images,
            'limits' => ['texts' => SiteSettingsForm::TEXT_LIMITS, 'email' => SiteSettingsForm::EMAIL_MAX, 'url' => SiteSettingsForm::URL_MAX],
            'private_keys' => $private,
        ];
    }

    /**
     * @return Collection<string, SiteSetting>
     */
    private static function settings(): Collection
    {
        $keys = [...SiteSettingsForm::textKeys(), ...array_keys(SiteSettingsForm::valueKeys()), ...SiteSettingsForm::imageKeys()];

        return SiteSetting::query()->whereIn('key', $keys)->with('translations')->get()->keyBy('key');
    }
}
