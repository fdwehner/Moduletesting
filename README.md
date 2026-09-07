# Moduletesting

Laravel 13 application with the same stack as [Family](https://github.com/fdwehner/Family). Follow [CONTRIBUTING.md](CONTRIBUTING.md) for code style, translations, security, Livewire, and pull request rules.

The product surface is **Org Designer** (from GIT org designer v2.0): ILT areas, team topologies, positions, Excel import/export, and a Big Picture view.

## Requirements

- PHP 8.3+ with extensions: `mbstring`, `xml`, `curl`, `zip`, `sqlite3` (or `mysql`), `bcmath`, `intl`, `gd`
- [Composer](https://getcomposer.org/)
- Node.js 22+ and npm (for Vite / Tailwind)

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
npm run build
php artisan serve
```

Register or log in, then open `/org-designer` to manage **your org charts** (stored in the database). Publish a finished chart so it appears at `/org-charts` for everyone.

The default database is SQLite (`DB_CONNECTION=sqlite`). Switch to MySQL by setting `DB_*` in `.env`.

## Development

```bash
composer run dev
```

This starts the Laravel server, queue worker, log watcher, and Vite.

## Tests

```bash
php artisan test
```

## Conventions already wired

- **English and German** translation files under `lang/en` and `lang/de`. All user-facing copy must use `__()` keys; shared strings live in `common.php`.
- **Livewire 4** for new forms, list pages, and modals (`app/Livewire/Forms`, `app/Livewire/Modals`).
- **Laravel policies** for authorization (`app/Policies`). Use `$this->authorize()`, not raw permission checks.
- **Upload rules** in `App\Support\UploadRules` — do not duplicate `mimes|max` strings.
- **Locale switch** at `POST /locale/{locale}` (`en` / `de`), stored in session.
- **OpenAI timeouts** in `config/services.php` (`OPENAI_TIMEOUT`, `OPENAI_LONG_TIMEOUT`).
- **Activity log channel** `activity` in `config/logging.php`.

Consultant UI uses `layouts.app` (Livewire + Tailwind, dark mode). The org designer uses `layouts.org-designer`. Keep any future client portal on a separate layout, views, and controllers as described in CONTRIBUTING.md.
