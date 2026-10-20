<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\SiteSettingsController;
use App\Http\Controllers\Api\TechnologyController;
use Illuminate\Support\Facades\Route;

/*
| Public API. Everything is read-only (GET) except the contact form, which only
| stores a new message for the administration inbox. The "api" middleware group is
| stateless (no session, no cookies). Rate limiting is applied once per route, with
| the "public-api" and "contact" limiters defined in AppServiceProvider.
*/

Route::middleware('throttle:public-api')->name('api.')->group(function () {
    Route::get('site-settings', SiteSettingsController::class)->name('site-settings');
    Route::get('technologies', [TechnologyController::class, 'index'])->name('technologies.index');
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    // Malformed slugs never reach the controller: they fall through to the generic 404.
    Route::get('projects/{slug}', [ProjectController::class, 'show'])
        ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
        ->name('projects.show');
});

Route::post('contact-messages', [ContactMessageController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('api.contact-messages.store');
