<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetAdminLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Saves the administration language (en / ar) in the session and returns to the admin page
 * the visitor came from. Only a same-site /admin address is used as the target.
 */
class AdminLocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $request->session()->put(SetAdminLocale::SESSION_KEY, $locale);

        $previous = url()->previous();
        $base = url('/admin');
        $target = str_starts_with($previous, $base.'/') || $previous === $base ? $previous : $base;

        return redirect()->to($target);
    }
}
