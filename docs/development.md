# Developer & API Reference Guide: INOTEK

This document provides developer guidelines, environment setup, testing commands, code styling conventions, WebSocket setups, and API endpoint references.

---

## 1. Key CLI Commands

INOTEK requires PHP 8.3+ (`composer.json` requires `^8.3`; 8.4 is recommended)
with the `gd` extension enabled, because certificate QR codes are rendered with
`bacon/bacon-qr-code`'s `GDLibRenderer`. Check the executable and loaded
configuration before running Artisan if multiple PHP versions are installed:
```powershell
where.exe php
php --ini
php -m   # should list gd
```
The first executable should be the PHP install you intend to use (for example
`C:\PHP\php-8.4\php.exe`), and the loaded configuration should be the `php.ini`
in that same directory. Open a new terminal after changing the machine PATH. If
startup warnings name an older PHP directory, that terminal is resolving a
different executable/configuration.

`composer setup` performs a first-time install (`composer install`, copies
`.env.example` to `.env` if missing, `key:generate`, `migrate --force`,
`npm install`, `npm run build`). `composer dev` starts `php artisan serve`,
`php artisan queue:listen --tries=1`, and `npm run dev` together.

### 1.1 Backend Environment Setup (PHP/Laravel)
Set up backend dependencies, migrations, and run testing verification:
```bash
# Install package dependencies
composer install

# Regenerate key and bootstrap database
php artisan key:generate
# Local development database only: deletes all existing tables/data.
php artisan migrate:fresh --seed

# Existing deployment after database backup:
php artisan migrate --force

# Run automated tests (needs a MySQL test database, see 1.3)
php artisan test
```

### 1.2 Frontend Environment Setup (Vite/React)
Set up client packages and compile output bundles:
```bash
# Install packages
npm install

# Start local hot-reloading development server
npm run dev

# Compile production-ready minified build assets
npm run build
```
> [!WARNING]
> Run `npm run build` from a mapped drive letter (e.g. `Z:\events\inotek`), not
> from a `\\server\share` UNC path. On a UNC path Vite fails with
> "Cannot resolve entry module index.html".

### 1.3 Running Tests (MySQL)
`phpunit.xml` defaults to SQLite `:memory:`, but eight migrations query
`information_schema`, so the suite must run against **MySQL 8**. Create an empty
test database once:
```sql
CREATE DATABASE inotek_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
Then override the `DB_*` values for the test run. PHPUnit's `<env>` entries in
`phpunit.xml` take precedence over a `.env.testing` file, but they do not
overwrite variables that already exist in the process environment, so set them
in the shell (you can keep the same values in `.env.testing` for reference):
```powershell
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_DATABASE = 'inotek_test'
$env:DB_USERNAME = 'root'
$env:DB_PASSWORD = ''
php artisan test
```
> [!NOTE]
> About 16 tests currently fail because they have not been updated for recent
> behaviour changes: `CompetitionSessionTest`, `ProjectRegistrationTest`,
> `CategoryCrudTest`, `ProjectVerificationTest`, and `NotificationSystemTest`.
> They are pending an update. Treat failures in any other class as regressions.

### 1.4 Legacy Data Import
`inotek:import-legacy-competition` copies registrations and submitted scores from
the legacy tables (`sessions`, `pendaftaran`, `score`) in the same database into
the normalized competition tables:
```bash
# Preview: prints counts per item, writes nothing
php artisan inotek:import-legacy-competition

# Import after reviewing the preview
php artisan inotek:import-legacy-competition --commit
```
The command refuses to run unless the selected database is `db4inotekdev` or
`db4inotek`, all required tables exist, and pending migrations have been applied.
The import runs in a single transaction. Imported sessions only receive `name`,
`is_active`, and `r2_locked`; set `registration_mode` and the session and
registration dates for each one in the admin session editor afterwards (the form
requires them).

---

## 2. Real-Time Services (WebSockets & Queues)

INOTEK supports queued system notifications and Laravel broadcasting. The default
`.env.example` configuration logs broadcast events locally; real-time WebSocket
delivery requires installing and configuring Laravel Reverb first.

> [!NOTE]
> Current state: `SystemNotification` is delivered via the `database` and
> `broadcast` channels, but with `BROADCAST_CONNECTION=log` and no Reverb
> installed the broadcast half only writes to the log. Users see notifications
> from the database channel; there is no live push yet.

### 2.1 Optional WebSocket Server (Laravel Reverb)
Reverb is currently documented but not enabled in the application dependency set.
The current Reverb release needs compatibility validation with Laravel 13 before
it can be enabled safely. Once that compatibility issue is resolved, install it:
```bash
composer require laravel/reverb
php artisan reverb:install
```
Then set `BROADCAST_CONNECTION=reverb` and the `REVERB_*` variables in `.env`.
Start the WebSocket server with:
```bash
php artisan reverb:start
```
> [!NOTE]
> Until Reverb is configured, use `BROADCAST_CONNECTION=log` for local development.
> In production, manage the Reverb process using a supervisor configuration.

### 2.2 Deployment and frontend assets

The production web root must point to Laravel's `public/` directory. After
deploying code changes, compile frontend assets with `npm run build` and include
the generated `public/build` files in the release. Back up the database before
running release migrations. Clear/rebuild Laravel caches after deployment as
appropriate:
```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
The changelog is cached for up to one hour. An administrator can clear this
specific cache from `/dashboard/changelog`; the registered endpoint is
`POST /dashboard/changelog/clear-cache`. A compatibility route also accepts the old `POST /changelog/clear-cache` URL for already-cached frontend bundles.

### 2.3 Production checklist

Before deployment, confirm:

- `APP_ENV=production`, `APP_DEBUG=false`, a generated `APP_KEY`, and HTTPS.
- Database credentials, `SESSION_SECURE_COOKIE=true`, and a non-local mail transport.
- Logging: `LOG_LEVEL=warning`, `LOG_STACK=daily`, `LOG_DAILY_DAYS=30`.
- **Outstanding:** SMTP (`MAIL_MAILER=smtp` plus `MAIL_*` credentials) and a
  `php artisan queue:work --tries=3` worker installed as a Windows service (e.g.
  with NSSM) are not set up yet. `MustVerifyEmail` is deliberately disabled until
  mail delivery works.
- On first production seeding, provide `INOTEK_BOOTSTRAP_ADMIN_EMAIL` and a unique `INOTEK_BOOTSTRAP_ADMIN_PASSWORD` of at least 16 characters through the deployment secret store. The seeder creates only this bootstrap admin in production; demo accounts and competition fixtures are limited to local/testing.
- `php artisan storage:link`, migrations, and a continuously running queue worker.
- Scheduled tasks, failed-job monitoring, database backups, and log rotation.
- Reverb credentials and a supervised `php artisan reverb:start` process if enabled.
- `npm run build` after every frontend change (from a mapped drive, not a UNC path).

Release flow on the server:
```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force   # after a database backup
php artisan optimize
npm run build
```

### 2.4 Queue Workers
System notifications are queued for database and broadcast delivery. Audit log writes
and certificate PDF generation run during the request, and judging reminder emails are
sent synchronously by the admin action. Run a worker to deliver queued notifications:
```bash
php artisan queue:work --queue=default
```
> [!IMPORTANT]
> Ensure `QUEUE_CONNECTION=database` (or another configured queue connection) and that
> the queue tables have been migrated. Monitor failed jobs in production.

---

## 3. Code Standards & Quality Check

Maintain clean code structures. Run these tools prior to committing modifications:

- **PHP Code Formatter (Laravel Pint)**:
  ```bash
  vendor/bin/pint
  ```
- **JS/React Code Formatter (Prettier)**:
  ```bash
  npm run format         # prettier --write resources/
  ```
- **JS/React Linter (ESLint)**:
  ```bash
  npm run lint:check     # or npm run lint to auto-fix
  ```
- **TypeScript Static Analyzer**:
  ```bash
  npm run types:check    # tsc --noEmit
  ```
- **PHP Static Analyzer (PHPStan/Larastan)**:
  ```bash
  composer types:check   # phpstan analyse
  ```

### 3.1 CI Check
`composer ci:check` runs the full pre-commit gate in one command:
`npm run lint:check`, `npm run format:check`, `npm run types:check`, and then
`composer test` (which clears config, runs Pint in test mode, PHPStan, and
`php artisan test`). The test step needs the MySQL test database from section
1.3, and the known stale tests listed there are expected to fail until updated.

---

## 4. API Endpoint Reference

The following endpoints are public or used for external system integrations:

### 4.1 Get Live Leaderboard Data
The public leaderboard route returns an Inertia page by default. Request JSON using
`Accept: application/json`, or append `?api=true` for the polling response.
- **Endpoint**: `GET /leaderboard`
- **Response Format**: `application/json` when requested
- **Example Response**:
```json
{
  "activeSession": { "id": 1, "name": "Semester 1 2026/2027", "is_active": true, "r2_locked": false, "...": "..." },
  "categories": [{ "id": 1, "code": "C1", "name": "Green Technology" }],
  "leaderboardData": {
    "1": [{ "id": 12, "title": "Smart Solar Grids", "pcode": "C1-02", "category_id": 1, "category_name": "Green Technology", "award_level": null }]
  }
}
```
`activeSession` is the full competition session model, not just `id` and `name`.

Before Round 2 is finalized (`r2_locked`), the public response omits scores and
`award_level` (always `null`) and ranks projects by project code. After
finalization, it includes `avg_r1`, `judges_r1`, `avg_r2`, `judges_r2`,
`final_score`, and `award_level`. Ranking within a category puts Round 2 finalists
(`judges_r2 > 0`) above projects judged only in Round 1, then orders by
`final_score` descending (the Round 2 average when Round 2 scores exist, otherwise
the Round 1 average).

The authenticated staff leaderboard (`GET /dashboard/leaderboard`, roles `admin`,
`lecturer`, `judge`) additionally returns participant names, judge names, individual
scores, comments, and presenter votes. Admins and judges always see these details;
lecturers see them only after `r2_locked`, and before that get the same restricted
view as the public board.

### 4.2 Verify Certificate Authenticity
Public verification endpoint mapped to certificate QR codes.
- **Endpoint**: `GET /verify/certificate/{hash}`
- **Parameters**:
  - `hash` (string, required) — Unique certificate signature hash.
- **Response Format**: Inertia page (`certificate/Verify`) only; there is no JSON
  variant. Only approved projects verify. The payload is deliberately minimal:
  `verified`, `hash`, and for a match the project's `id`, `title`, `pcode`,
  `status`, `certificate_hash`, `award_level`, `user.name`, `category.name`/`code`,
  `session.name`, and team member names. No contact details or comments are
  exposed. `award_level` is `null` until the session's Round 2 is locked
  (`r2_locked`).

---

## 5. Language & Localization Standards

To maintain standard global usability:
- **Interface Language**: All user interfaces, pages, labels, menus, alerts, and buttons must be written exclusively in **English**.
- **System Messages**: All validation errors, redirect flash messages, email templates, and exception messages returned to the frontend must be written in **English**.
