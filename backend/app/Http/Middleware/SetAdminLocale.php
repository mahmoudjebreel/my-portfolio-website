<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface language of the administration panel: the choice saved in the session
 * (AdminLocaleController), else the application default. Arabic switches Filament to
 * right-to-left automatically. The public React site is not affected.
 */
class SetAdminLocale
{
    public const SESSION_KEY = 'admin_locale';

    public const LOCALES = ['en', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        if (in_array($locale, self::LOCALES, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
