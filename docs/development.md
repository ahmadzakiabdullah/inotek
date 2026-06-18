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

INOTEK relies on background queue processing and WebSockets for real-time leaderboards.

### 2.1 WebSocket Server (Laravel Reverb)
Start the WebSocket server to broadcast real-time scoring updates:
```bash
php artisan reverb:start
```
> [!NOTE]
> In production environments, manage this process using a supervisor configuration to automatically restart the service if it crashes.

### 2.2 Queue Workers (Background Tasks)
Tasks like sending email notifications, audit processing, and certificate generations run asynchronously:
```bash
php artisan queue:work --queue=default,emails
```
> [!IMPORTANT]
> Ensure the `QUEUE_CONNECTION` environment variable is set to `database` in your `.env` file for these tasks to execute.

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

### 4.1 Get Live Leaderboard Scores
Retrieves the real-time rankings of projects sorted by overall weighted score.
- **Endpoint**: `GET /api/leaderboard`
- **Response Format**: `application/json`
- **Example Response**:
```json
{
  "session": "Semester 1 2026/2027",
  "leaderboard": [
    {
      "rank": 1,
      "project_code": "C1-002",
      "title": "Smart Solar Grids",
      "category": "Green Technology",
      "average_score": 94.20
    },
    {
      "rank": 2,
      "project_code": "C3-005",
      "title": "AI Autonomous Drone Patrol",
      "category": "Emerging Technology",
      "average_score": 89.50
    }
  ]
}
```

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
