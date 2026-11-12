# INOTEK

INOTEK is a next-generation web application designed to manage innovation and technology competitions at Universiti Teknikal Malaysia Melaka (UTeM). Built on **Laravel 13**, **Inertia.js**, and **React**, it features a premium dashboard user interface powered by **Shadcn UI** and styled with **Tailwind CSS v4**.

> [!NOTE]
> This repository represents the modernized core codebase, consolidating the legacy procedural PHP system into a secure, single-page application framework.

> [!NOTE]
> Current operating model (October 2026): project, approval, score, and judging data belong to a competition session. Dashboard pages default to the active session. Categories are shared globally across sessions; project codes restart for each session. See [the PRD](docs/prd.md), [database reference](docs/database.md), and [changelog](docs/changelog.md).

---

## 🌟 Key Features

- 📊 **Role-Specific Dashboard**: Summaries for the active session. Admins see project status counts, recent registrations, judging progress per category, and judges with pending evaluations; lecturers see the projects they supervise; participants see their own project; judges are redirected to their evaluation list.
- 👤 **Account & Profile Management**: Complete user profile update page with custom `username` validations.
- 🔐 **Dual-Option Authentication**: Flexibly logs in users using either their **Email or Username** via Laravel Fortify.
- 🛡️ **Role-Based Access Control**: Strict database-backed roles (`admin`, `lecturer`, `judge`, `user`) secured via the `RoleMiddleware` engine. Project registration and submission are limited to the participant role (`role:user`).
- 👥 **Users CRUD Datatable**: Admin dashboard for complete account controls featuring client-side search, multi-column sorting, and pagination.
- 🎛️ **Roles CRUD Dashboard**: Admin panel to add, edit, and safely delete custom roles. The system roles are protected by name (they cannot be renamed or deleted), and a role that still has users cannot be deleted. The same safeguard applies to competition sessions with projects and categories with projects.

---

## 💻 Tech Stack

* **Backend**: Laravel 13, PHP 8.3+ (8.4 recommended) with the `gd` extension (certificate QR codes are rendered with `bacon/bacon-qr-code`'s `GDLibRenderer`)
* **Frontend**: React 19, Inertia.js (React), Vite
* **Database**: MySQL 8.0 / MariaDB
* **Design & Styling**: Tailwind CSS v4, Shadcn/UI (Mira preset), Tabler Icons

---

## 🚀 Installation & Local Setup

Follow these steps to configure your local environment (e.g., Laragon):

For production deployment and the pre-commit quality checks (`composer ci:check`), see the [developer guide](docs/development.md).

> [!TIP]
> `composer setup` runs steps 1, 2 and the migrations in one go (`composer install`, copies `.env.example` to `.env` if missing, `key:generate`, `migrate --force`, `npm install`, `npm run build`). Update the database credentials in `.env` before running it. `composer dev` then starts `php artisan serve`, a queue listener, and the Vite dev server together.

### 1. Clone & Dependencies
Clone the codebase and install backend and frontend packages:
```bash
composer install
npm install
```

### 2. Configuration Setup
Copy the environment variables template and generate the application security key:
```bash
cp .env.example .env
php artisan key:generate
```
> [!IMPORTANT]
> Ensure you update the database credentials (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) in your `.env` file to match your local database settings before running migrations.

### 3. Database Migration & Seeding
For a fresh local development database only, create tables and seed local data:
```bash
php artisan migrate:fresh --seed
```
> [!WARNING]
> `migrate:fresh` deletes all tables and data. Never use it on production. For an existing deployment, back up the database and run `php artisan migrate --force`.

New registrations receive the `user` role. The system roles are `admin`, `lecturer`, `judge`, and `user`; role IDs must not be hard-coded in application logic.

#### Importing legacy competition data (optional)
Registrations and submitted scores from the legacy system (`sessions`, `pendaftaran`, `score` tables in the same database) can be imported into the normalized competition tables:
```bash
# Preview only: prints counts, writes nothing
php artisan inotek:import-legacy-competition

# Write the import after reviewing the preview
php artisan inotek:import-legacy-competition --commit
```
The command only runs against a database named `db4inotekdev` or `db4inotek` and requires pending migrations to be applied first. Imported sessions do not carry a registration mode or dates, so edit each one under the admin session screen and set `registration_mode` and the session/registration dates before using it.

### 4. Compiling Assets
```bash
# Start local development server (Vite Hot Module Replacement)
npm run dev

# Compile minified production assets
npm run build
```

---

## 🧪 Running Tests
Run the PHPUnit suite to verify access controls and endpoint security:
```bash
php artisan test
```
> [!IMPORTANT]
> The tests need **MySQL 8**. `phpunit.xml` defaults to SQLite `:memory:`, but eight migrations query `information_schema` and fail on SQLite. Create an empty test database (e.g. `CREATE DATABASE inotek_test;`) and point the test run at it with `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE=inotek_test`, `DB_USERNAME`, `DB_PASSWORD`. Set these as shell environment variables (or keep them in a `.env.testing` and export them): PHPUnit's `<env>` entries in `phpunit.xml` win over `.env.testing`, but do not overwrite variables that already exist in the environment. See the [developer guide](docs/development.md#13-running-tests-mysql).
>
> About 16 tests are known to fail because they have not been updated for recent behaviour changes (`CompetitionSessionTest`, `ProjectRegistrationTest`, `CategoryCrudTest`, `ProjectVerificationTest`, `NotificationSystemTest`). They are pending an update; treat failures outside those classes as real regressions.

---

## 📄 Documentation

For detailed technical specifications and developer handbooks, refer to the documents in the `docs/` folder:

* 📋 **[Product Requirements Document (PRD)](docs/prd.md)**: Details the functional requirements, user roles, state machines, and business rules.
* 🗄️ **[Database Schema Reference](docs/database.md)**: Entity Relationship Diagram (ERD) and detailed table definitions.
* 🛠️ **[Developer & API Reference Guide](docs/development.md)**: Coding standards, testing instructions, optional WebSocket setup, and API endpoints.
* 🗺️ **[Development Roadmap (TODOS)](docs/todos.md)**: Active task checklist and implementation gantt phases.

## Changelog

See [the changelog](docs/changelog.md) for the current release and version history.
