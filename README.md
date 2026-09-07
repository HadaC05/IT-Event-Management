# IT Event Management

A Laravel application for managing school events, users, tribes, attendance, scores, rankings, announcements, and reports.

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js and npm
- SQLite (default) or a configured MySQL database

## First-time setup

```bash
git clone https://github.com/HadaC05/IT-Event-Management.git
cd IT-Event-Management
composer run setup
composer run dev
```

`composer run setup` installs PHP and JavaScript dependencies, creates the local `.env` and SQLite database when missing, generates the application key when needed, runs migrations, and builds the frontend assets.

## Sync after pulling changes

```bash
git pull --ff-only
composer run sync
```

Then start the application for development:

```bash
composer run dev
```

The `sync` command installs locked dependencies, clears Laravel's cached files, applies pending migrations, and rebuilds the frontend. It does not replace `.env`, regenerate `APP_KEY`, erase the database, or seed duplicate records.

## Team workflow

Before beginning work each day:

```bash
git switch your-branch-name
git pull --ff-only
composer run sync
composer run dev
```

Create a separate branch for each feature or fix and merge it through a pull request. Commit source files such as Blade templates, PHP, CSS, migrations, and tests. Do not commit local-only or generated files, including `.env`, `vendor`, `node_modules`, or `public/build`.

Never use `php artisan migrate:fresh` on a database whose data must be preserved. Normal updates use `php artisan migrate`, which is already included in `composer run sync`.

## Tests

```bash
composer test
```
