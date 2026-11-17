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
> All tests are expected to pass (197 as of 15 November 2026). Inertia page tests
> need `public/build/manifest.json`, so run `npm run build` (or copy the build
> folder) first; otherwise they fail with "Not a valid Inertia response".

### 1.4 Legacy Data
The legacy tables (`sessions`, `pendaftaran`, `score`) were imported once into
Sesi 1/2026 (competition session ID 1). The one-time
`inotek:import-legacy-competition` command was then removed, because running it
again would copy the legacy `is_active` and `r2_locked` flags back onto the
competition sessions; it remains in the git history (commit `e4312ad` and
earlier). The legacy system recorded the June 2026 judging under "Sesi 2/2026"
only because that session was active at the time; all of it belongs to Sesi 1/2026.

Two follow-up commands remain. Both preview by default and write only with `--commit`:
```bash
# Copy Best Presenter nominations from the legacy score table (done for Sesi 1/2026)
php artisan inotek:backfill-legacy-presenters --session=1

# Set award levels from a CSV of project code and award (see section 1.5)
php artisan inotek:import-awards awards.csv --session=1
```

### 1.5 Importing Award Levels
Awards are normally set one project at a time on the Round 2 screen, which only
accepts Round 2 finalists. When a session's official results were decided
elsewhere (as for Sesi 1/2026, judged in the legacy system), import them from a
CSV instead. In Excel use *Save As > CSV UTF-8*. The first column is the project
code and the second the award; a header row is optional:

```csv
Kod Projek,Anugerah
GT-01,Gold
SE-05,Perak
```

Awards may be written `Gold`/`Emas`, `Silver`/`Perak`, `Bronze`/`Gangsa`, or
`None`/`Tiada` to clear one. Commas, semicolons and tabs all work as separators.

```bash
# Preview: lists what will change and any problem rows, writes nothing
php artisan inotek:import-awards awards.csv --session=1

# Write after reviewing the preview
php artisan inotek:import-awards awards.csv --session=1 --commit
```

Nothing is written while any row has a problem (unknown code, project not
approved, unknown award, or a code listed twice with different awards).
Projects missing from the file keep their current award. Awards stay hidden from
participants until Round 2 is finalized for the session.

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
- Mail (set up 15 November 2026): `MAIL_MAILER=smtp`, `MAIL_HOST=10.1.3.16`,
  `MAIL_PORT=25`, no TLS and no authentication (UTeM relay `smtp01.utem.edu`),
  `MAIL_FROM_ADDRESS="webmaster@utem.edu.my"`. Check delivery from
  `/dashboard/email` → Send Test Email. `MustVerifyEmail` is still disabled.
- Queue worker: see section 2.5.
- On first production seeding, provide `INOTEK_BOOTSTRAP_ADMIN_EMAIL` and a unique `INOTEK_BOOTSTRAP_ADMIN_PASSWORD` of at least 16 characters through the deployment secret store. The seeder creates only this bootstrap admin in production; demo accounts and competition fixtures are limited to local/testing.
- `php artisan storage:link`, migrations, and a continuously running queue worker.
- Scheduled tasks, failed-job monitoring, database backups, and log rotation.
- Reverb credentials and a supervised `php artisan reverb:start` process if enabled.
- `npm run build` after every frontend change (from a mapped drive, not a UNC path).

Release flow used for production (`inotek.utem.edu.my`, IIS, code served from
the git working tree on `\\10.1.2.22\e\events\inotek`):

1. Work and test in a separate clone on the developer machine (MySQL test
   database, section 1.3). Never point the tests at the production database:
   `RefreshDatabase` wipes it.
2. Run `npm run build` in that clone, then push a branch, open a pull request
   and merge it into `main`.
3. On the server share: `git pull --ff-only`.
4. Copy the clone's `public/build` to the server: `public/build/assets` first,
   `public/build/manifest.json` **last**, so the live manifest never points at
   files that are not there yet. Building directly on the share is slow and has
   hung.
5. Only when the release has migrations: back up the database in phpMyAdmin
   (the server has no `mysqldump`), confirm the backup, and only then run
   `php artisan migrate --force` on the server as a separate step.
6. `php artisan optimize` if configuration or routes changed, and clear the
   changelog cache from `/dashboard/changelog` after a version bump.

Run `composer install --no-dev --optimize-autoloader` on the server only when
`composer.lock` changed.

### 2.4 IIS and HTTP methods
IIS rejects `PUT`, `PATCH` and `DELETE` with `405 Method Not Allowed` before the
request reaches Laravel. Frontend code must therefore:
- send updates and deletes as `POST` with Laravel method spoofing, for example
  `form.transform((data) => ({ ...data, _method: 'put' }))` followed by
  `form.post(url)`, or `router.post(url, { _method: 'delete' })`; or
- call a dedicated `POST` alias route (for example `admin.assignments.delete`,
  `admin.rubrics.update`, `admin.categories.save`).

Wayfinder `.form()` helpers already add `?_method=…` and are safe. Feature tests
use real `PUT`/`DELETE`, so they do not catch this; add a test that posts with
`_method` when introducing a new update or delete action.

**Firewall and client IP.** Every request reaches IIS through the campus firewall at
`10.1.2.9`. `bootstrap/app.php` trusts only that address, and only its
`X-Forwarded-For` and `X-Forwarded-Proto` headers, so rate limits and audit logs see
the visitor's real IP. If the firewall address changes, update it there.

**Security headers.** `App\Http\Middleware\SecurityHeaders` adds `X-Frame-Options`,
`X-Content-Type-Options`, `Referrer-Policy` and (on HTTPS in production)
`Strict-Transport-Security` to every Laravel response, and removes `X-Powered-By`.

**Dependency checks.** Run `composer audit` and `npm audit --omit=dev` before a release.
After `composer.lock` changes, run `composer install --no-dev --optimize-autoloader`
in `E:\events\inotek` on the server.

The site sits behind the university's Fortinet firewall (it sets a
`cookiesession1` cookie). Console errors mentioning `cookiesession…` or an
`about:srcdoc` iframe come from the firewall, not from INOTEK.

### 2.5 Queue Workers
System notifications (`SystemNotification`) are queued (`QUEUE_CONNECTION=database`)
for the database, broadcast and, when enabled, mail channels. Audit log writes,
certificate PDF generation, judge reminder emails and the test email run during the
request. Without a worker, notifications wait in the `jobs` table and never arrive.

In production a Windows scheduled task runs the worker every minute; each run
processes what is waiting and exits:
```powershell
schtasks /Create /TN "INOTEK Queue Worker" /SC MINUTE /MO 1 /RU "webpool-iis\web" /RP * /F /TR "E:\PHP\php-8.4.16\php.exe E:\events\inotek\artisan queue:work --stop-when-empty --tries=3 --max-time=50"
schtasks /Query /TN "INOTEK Queue Worker" /V /FO LIST   # Last Result: 0 means OK
```
The task runs as `webpool-iis\web` with its stored password (the account cannot create
SYSTEM tasks). **If that account's password changes, recreate the task with the new
password**, or notifications stop again. The Email & Notifications page warns when a
queued notification has waited more than five minutes.

Process the queue by hand, or clear permanently failed jobs:
```bash
php artisan queue:work --stop-when-empty --tries=3
php artisan queue:failed     # list
php artisan queue:flush      # delete all failed jobs
```
The same retry and discard actions are buttons under Email & Notifications → Settings →
Delivery Status whenever failed jobs exist.

### 2.6 Scheduler and Automatic Cleanup
`routes/console.php` schedules `inotek:prune` daily at 03:00. It deletes email log rows
older than 180 days, read notifications older than 90 days and failed jobs older than
30 days. Audit logs, projects and scores are never deleted. A second scheduled task runs
the Laravel scheduler every minute:
```powershell
schtasks /Create /TN "INOTEK Scheduler" /SC MINUTE /MO 1 /RU "webpool-iis\web" /RP * /F /TR "E:\PHP\php-8.4.16\php.exe E:\events\inotek\artisan schedule:run"
schtasks /Query /TN "INOTEK Scheduler" /V /FO LIST
```
The same password rule applies as for the queue worker. Run or preview the cleanup by hand:
```bash
php artisan inotek:prune --dry-run   # counts only
php artisan inotek:prune
```
The Automatic Cleanup card on the Email & Notifications page shows the last run and warns
when the cleanup has not run for two days.

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
1.3, and all tests are expected to pass.

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
scores, comments, and presenter votes. Admins always see these details. Judges
see only their own scores and comments (no averages, awards or presenter votes)
until `r2_locked`. Lecturers see them only after `r2_locked`, and before that get
the same restricted view as the public board.

### 4.3 Admin Report Downloads
Authenticated admin-only downloads used by the Reports & Export page.
- **Endpoint**: `GET /dashboard/reports/{type}.{format}`
- **Parameters**:
  - `type`: `results`, `participants`, `judge-scores` or `judging-status`.
  - `format`: `csv` (UTF-8 with BOM, opens in Excel) or `pdf`.
  - `session_id` (query, required): the competition session to report on.
- Each download is recorded in the audit log.

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
