# Developer & API Reference Guide: INOTEK

This document provides developer guidelines, environment setup, testing commands, code styling conventions, WebSocket setups, and API endpoint references.

---

## 1. Key CLI Commands

### 1.1 Backend Environment Setup (PHP/Laravel)
Set up backend dependencies, migrations, and run testing verification:
```bash
# Install package dependencies
composer install

# Regenerate key and bootstrap database
php artisan key:generate
php artisan migrate:fresh --seed

# Run automated tests
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

---

## 2. Real-Time Services (WebSockets & Queues)

INOTEK supports queued system notifications and Laravel broadcasting. The default
`.env.example` configuration logs broadcast events locally; real-time WebSocket
delivery requires installing and configuring Laravel Reverb first.

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

### 2.2 Production checklist

Before deployment, confirm:

- `APP_ENV=production`, `APP_DEBUG=false`, a generated `APP_KEY`, and HTTPS.
- Database credentials, `SESSION_SECURE_COOKIE=true`, and a non-local mail transport.
- `php artisan storage:link`, migrations, and a continuously running queue worker.
- Scheduled tasks, failed-job monitoring, database backups, and log rotation.
- Reverb credentials and a supervised `php artisan reverb:start` process if enabled.

### 2.3 Queue Workers
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
  npx prettier --write "resources/js/**/*.{js,ts,tsx}"
  ```
- **TypeScript Static Analyzer**:
  ```bash
  npx tsc --noEmit
  ```

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
  "activeSession": { "id": 1, "name": "Semester 1 2026/2027" },
  "categories": [{ "id": 1, "code": "C1", "name": "Green Technology" }],
  "leaderboardData": {
    "1": [{ "id": 12, "title": "Smart Solar Grids", "pcode": "C1-02", "category_id": 1, "category_name": "Green Technology", "award_level": null }]
  }
}
```
Before Round 2 is finalized, the public response omits scores and ranks projects by
project code. After finalization, it includes final scores and ranks by score. Judge
identities, individual scores, comments, participant names, and presenter votes are
only returned by the authenticated staff leaderboard.

### 4.2 Verify Certificate Authenticity
Public verification endpoint mapped to certificate QR codes.
- **Endpoint**: `GET /verify/certificate/{hash}`
- **Parameters**:
  - `hash` (string, required) — Unique certificate signature hash.
- **Response Format**: HTML (Renders a verification template) or JSON. Validates the student's name, project title, award level (Gold/Silver/Bronze), and semester details.

---

## 5. Language & Localization Standards

To maintain standard global usability:
- **Interface Language**: All user interfaces, pages, labels, menus, alerts, and buttons must be written exclusively in **English**.
- **System Messages**: All validation errors, redirect flash messages, email templates, and exception messages returned to the frontend must be written in **English**.
