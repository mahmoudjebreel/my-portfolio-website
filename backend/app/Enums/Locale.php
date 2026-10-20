<?php

namespace App\Enums;

enum Locale: string
{
    case En = 'en';
    case Ar = 'ar';

    /**
     * Text direction for this locale.
     */
    public function direction(): string
    {
        return match ($this) {
            self::En => 'ltr',
            self::Ar => 'rtl',
        };
    }
}
