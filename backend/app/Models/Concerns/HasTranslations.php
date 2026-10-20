<?php

namespace App\Models\Concerns;

use App\Enums\Locale;
use Illuminate\Database\Eloquent\Model;

/**
 * Requires the using model to define a translations() HasMany relation
 * whose related model has a `locale` column.
 */
trait HasTranslations
{
    /**
     * The translation for exactly the given locale, or null.
     *
     * Strict policy: there is deliberately NO fallback to another language.
     */
    public function translation(Locale|string $locale): ?Model
    {
        $locale = $locale instanceof Locale ? $locale->value : $locale;

        if ($this->relationLoaded('translations')) {
            return $this->translations->first(
                fn (Model $translation) => $this->localeValue($translation) === $locale
            );
        }

        return $this->translations()->where('locale', $locale)->first();
    }

    private function localeValue(Model $translation): ?string
    {
        $value = $translation->getAttribute('locale');

        return $value instanceof Locale ? $value->value : $value;
    }
}
