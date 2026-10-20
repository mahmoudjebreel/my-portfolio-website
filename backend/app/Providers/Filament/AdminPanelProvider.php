<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Http\Controllers\AdminAvatarController;
use App\Http\Controllers\AdminMediaPreviewController;
use App\Http\Middleware\SetAdminLocale;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The administration panel (/admin), replacing the React administration (A1–A7).
 *
 * - Laravel "web" session guard; only is_admin accounts (User::canAccessPanel, custom Login).
 * - CSRF on every form and Livewire request (PreventRequestForgery); AuthenticateSession
 *   ends other sessions of an account when its password changes.
 * - Dark / light mode (Filament's toggle, saved in the browser); English / Arabic with
 *   right-to-left for Arabic (SetAdminLocale + the language item in the user menu).
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            // "My profile": name, email, password and account picture of the signed-in account.
            ->profile(EditProfile::class, isSimple: false)
            ->brandName(fn (): string => __('admin.brand'))
            ->colors([
                'primary' => Color::Violet,
                'gray' => Color::Slate,
            ])
            ->darkMode(true)
            // Local initials avatar: no request to ui-avatars.com.
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->userMenuItems([
                Action::make('locale')
                    ->label(fn (): string => __('admin.switch_to'))
                    ->icon(Heroicon::OutlinedLanguage)
                    ->url(fn (): string => route('admin-panel.locale', ['locale' => __('admin.switch_to_locale')])),
            ])
            // "Back to the website" button in the top bar (config app.frontend_url).
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): View => view('filament.admin.back-to-site'),
            )
            // Language switch on the sign-in page too (no user menu there).
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): View => view('filament.admin.locale-switch'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetCacheHeaders::using('no_store;private'),
            ])
            // Also applied to Livewire requests made from panel pages.
            ->middleware([SetAdminLocale::class], isPersistent: true)
            // Media previews for the panel (administrator session required; no storage:link needed).
            ->authenticatedRoutes(function (): void {
                Route::get('media-preview/{kind}/{id}', AdminMediaPreviewController::class)
                    ->whereIn('kind', ['cover', 'gallery'])
                    ->whereNumber('id')
                    ->name('media-preview');
                // The signed-in account's own picture (no user parameter).
                Route::get('account/avatar', AdminAvatarController::class)->name('account-avatar');
            })
            ->navigationGroups([
                NavigationGroup::make(fn (): string => __('admin.nav.content')),
                NavigationGroup::make(fn (): string => __('admin.nav.inbox')),
                NavigationGroup::make(fn (): string => __('admin.nav.site')),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
