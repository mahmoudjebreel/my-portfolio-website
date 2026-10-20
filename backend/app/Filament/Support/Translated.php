<?php

namespace App\Filament\Support;

use App\Enums\Locale;
use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

/**
 * Picks a translated value for the administration: the panel language first, else the other
 * language (the admin must always see something to recognise a record). The public API keeps
 * its strict no-fallback policy; this helper is used by the panel only.
 */
final class Translated
{
    /**
     * @param  Collection<int, Model>  $translations  loaded translations (any locale)
     */
    public static function pick(Collection $translations, string $field): ?string
    {
        $current = App::getLocale() === Locale::Ar->value ? Locale::Ar : Locale::En;
        $other = $current === Locale::Ar ? Locale::En : Locale::Ar;

        foreach ([$current, $other] as $locale) {
            $value = $translations->first(fn ($t) => $t->locale === $locale)?->getAttribute($field);
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    public static function categoryName(Category $category): ?string
    {
        return self::pick($category->translations, 'name');
    }
}
