<?php

use App\Http\Controllers\AdminLocaleController;
use App\Http\Middleware\SetAdminLocale;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Administration panel language (Filament, /admin): saved in the session only.
Route::get('admin/locale/{locale}', AdminLocaleController::class)
    ->whereIn('locale', SetAdminLocale::LOCALES)
    ->name('admin-panel.locale');
