# Developer Portfolio

Personal 3D portfolio: a React + TypeScript + Three.js front end (`frontend/`) and a Laravel 13 API (`backend/`) on MySQL.

> Work in progress. Sections below are added as each phase is approved.

## Local environment (Laragon)

Always call Laragon's PHP explicitly. The `php` on the system PATH is a different install (lower upload limits, missing extensions).

```powershell
$PHP = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"
```

Inside the Laragon terminal (Cmder), `php` already resolves to Laragon's PHP.

## Database

| Database | Purpose |
|---|---|
| `jebreel_portfolio` | Development data. Changes to it need an explicit decision. |
| `jebreel_portfolio_test` | Automated tests only. Wiped and rebuilt by every test run. |

The application connects as the restricted user `jebreel_dev`, which has privileges on these two databases only. Credentials live in `backend/.env`, which is never committed. Never use `root` for the application.

### Safe commands (run from `backend/`)

```powershell
& $PHP artisan migrate:status   # read-only: shows which migrations ran
& $PHP artisan migrate          # applies pending migrations only; does not drop tables
& $PHP artisan test             # runs only against jebreel_portfolio_test (guarded)
```

### Destructive commands: do not run on the development database without a deliberate decision

> **Warning:** the commands below drop or empty tables. All data in `jebreel_portfolio` is lost, including projects, messages and settings.

| Command | Effect |
|---|---|
| `migrate:fresh` | Drops **all** tables, then re-runs every migration |
| `migrate:refresh` | Rolls back **all** migrations, then runs them again |
| `migrate:reset` | Rolls back **all** migrations |
| `migrate:rollback` | Rolls back the last batch (drops those tables) |
| `db:wipe` | Drops **all** tables, views and types |

Take a backup before any of them:

```powershell
& "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe" -u root -h 127.0.0.1 jebreel_portfolio > jebreel_portfolio_backup.sql
```

### Seeding

- `database/seeders/DatabaseSeeder.php` contains only reviewed, approved initial data. It is empty for now, and it never creates users.
- Model factories (`database/factories`) are **test-only**. They throw an exception outside the `testing` environment, so `db:seed`, `migrate --seed` or tinker cannot insert fake users or data into a real database.
- The admin account is created by its owner with `php artisan admin:create` (see [Creating the administrator](#creating-the-administrator-run-by-the-owner)). No default credentials exist.

### Tests

`phpunit.xml` forces `DB_CONNECTION=mysql` and `DB_DATABASE=jebreel_portfolio_test`. In addition, `tests/TestCase.php` refuses to run any test, before any query, unless the connection points at `jebreel_portfolio_test`.

## Administration (`/admin`, Laravel Filament)

The administration is a [Filament](https://filamentphp.com) 5 panel served by Laravel at `/admin` (`backend/app/Providers/Filament/AdminPanelProvider.php`). The public React site does not contain any administration code, and the API under `/api` is the public, read-only API only.

| Section | What it does |
|---|---|
| Dashboard | Real figures from the database (projects, categories, technologies, unread messages) and the latest projects and messages |
| Projects | List (search, filters, sorting), create and edit in English and Arabic, publish / unpublish. Publishing requires complete English **and** Arabic content |
| Categories / Technologies | List, create, edit. A technology's public visibility is chosen only when it is created (hidden by default) |
| Images & media | Project covers and gallery images; upload adds a gallery image (JPG / PNG / WebP up to 5 MB, re-encoded to WebP, server-made file name) |
| Contact messages | Inbox (newest first, search, filter), message shown as plain text, mark read / unread, `mailto:` reply link. Nothing is sent automatically |
| Site settings | The keys of `SiteSettingsRegistry` only, in English and Arabic; saved only with the Save button |
| My profile | Name, email, password (at least 12 characters, current password required) and account picture |

Nothing is ever deleted from the administration: there is no delete action for projects, categories, technologies, images or messages.

Business rules live in shared classes used by the panel: `app/Actions/*` (saving, publishing, uploads, settings, read state) and the static rules of `app/Http/Requests/Admin/*Request.php` (`rulesFor` / `afterFor` / `messagesFor`), applied in the panel by `App\Filament\Support\SharedValidation`.

### Access and security

- **Administrators only**: `User::canAccessPanel()` requires `users.is_admin = true`, and the sign-in query itself includes `is_admin = true` (a regular account gets the same message as a wrong password). `is_admin` is not mass assignable.
- **Sessions**: Laravel's `web` session guard, 120 minutes of inactivity, no "remember me". Signing in regenerates the session. Changing the password in "My profile" keeps the current session and ends the account's other panel sessions (`AuthenticateSession`).
- **Rate limiting** (`App\Support\AdminLoginThrottle`, on top of Filament's 5 attempts per minute per address): 5 failed attempts per email + address per minute and 20 attempts per address per minute; IPv6 addresses are grouped by /64. Failed sign-ins take at least 500 ms (`auth.timebox_duration`).
- **CSRF**: every form and Livewire request goes through Laravel's `PreventRequestForgery` (verified for real in `tests/Feature/Admin/FilamentCsrfTest.php`).
- **Files**: image previews and account pictures are served by authenticated panel routes (`/admin/media-preview/…`, `/admin/account/avatar`), only for safe paths and raster images; stored paths are never shown. Account pictures are stored privately at `storage/app/private/avatars/{user id}.webp`.
- **Languages**: English or Arabic (right-to-left), switched from the account menu or the sign-in page and kept in the session. Dark / light mode from the account menu (kept in the browser).
- **Privacy**: avatars without a picture are drawn locally (no request to an outside avatar service).

### Creating the administrator (run by the owner)

```powershell
cd backend
& $PHP artisan admin:create
```

The command asks for the name, the email and the password (typed twice, hidden). The password needs at least 12 characters (72 bytes at most); it is never accepted as an argument, printed or logged. If the email already belongs to an account, the command stops without changing anything. It writes to the database configured in `backend/.env`. The administrator then signs in at `/admin/login`.

### Development

Run Laravel (`php artisan serve`) and the Vite dev server. The Vite server proxies `/admin`, Livewire (`/livewire…`) and the panel's assets (`/js/filament`, `/css/filament`, `/fonts/filament`) to Laravel (`VITE_API_PROXY_TARGET`, default `http://127.0.0.1:8010`), so the site and the panel share one origin; the panel is also reachable directly on the Laravel server. After updating Filament, `php artisan filament:upgrade` republishes its assets (it runs automatically after `composer install` / `update`).

### Production checklist (not testable locally)

- The web server sends `/admin`, `/livewire-…`, `/js/filament`, `/css/filament`, `/fonts/filament` and `/api` to Laravel (`backend/public/index.php`), and everything else to the built SPA.
- HTTPS, with `SESSION_SECURE_COOKIE=true`; `APP_DEBUG=false`; `CORS_ALLOWED_ORIGINS` stays empty.
- `php artisan storage:link` on the server, so that public pages can show project images from `storage/app/public` (done locally: `backend/public/storage` is a link, ignored by Git). The administration does not need it.
- PHP `upload_max_filesize` / `post_max_size` of at least 5 MB / 8 MB, otherwise uploads larger than the PHP limit are refused before Laravel sees them (the local Laragon PHP currently allows 2 MB).

Tests: `& $PHP artisan test` (database tests run only on `jebreel_portfolio_test`; see [Tests](#tests)). Panel tests are in `tests/Feature/Filament` and `tests/Feature/Admin`.
