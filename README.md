# INOTEK

INOTEK is a next-generation web application designed to manage innovation and technology competitions at Universiti Teknikal Malaysia Melaka (UTeM). Built on **Laravel 12**, **Inertia.js**, and **React**, it features a premium dashboard user interface powered by **Shadcn UI** and styled with **Tailwind CSS v4**.

> [!NOTE]
> This repository represents the modernized core codebase, consolidating the legacy procedural PHP system into a secure, single-page application framework.

---

## 🌟 Key Features

- 📊 **Dynamic Showcase Dashboard**: Real-time charts, recent transactions table, and team member summaries.
- 👤 **Account & Profile Management**: Complete user profile update page with custom `username` validations.
- 🔐 **Dual-Option Authentication**: Flexibly logs in users using either their **Email or Username** via Laravel Fortify.
- 🛡️ **Role-Based Access Control**: Strict database-backed roles (`admin`, `lecturer`, `judge`, `user`) secured via the `RoleMiddleware` engine.
- 👥 **Users CRUD Datatable**: Admin dashboard for complete account controls featuring client-side search, multi-column sorting, and pagination.
- 🎛️ **Roles CRUD Dashboard**: Admin panel to add, edit, and safely delete custom roles with system-protected safeguard blocks.

---

## 💻 Tech Stack

* **Backend**: Laravel 12, PHP 8.4+
* **Frontend**: React 19, Inertia.js (React), Vite
* **Database**: MySQL 8.0 / MariaDB
* **Design & Styling**: Tailwind CSS v4, Shadcn/UI (React components), Lucide Icons

---

## 🚀 Installation & Local Setup

Follow these steps to configure your local environment (e.g., Laragon):

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
Run migrations to generate tables, system roles, and demo users:
```bash
php artisan migrate:fresh --seed
```

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

---

## 📄 Documentation

For detailed technical specifications and developer handbooks, refer to the documents in the `docs/` folder:

* 📋 **[Product Requirements Document (PRD)](docs/prd.md)**: Details the functional requirements, user roles, state machines, and business rules.
* 🗄️ **[Database Schema Reference](docs/database.md)**: Entity Relationship Diagram (ERD) and detailed table definitions.
* 🛠️ **[Developer & API Reference Guide](docs/development.md)**: Coding standards, testing instructions, Reverb WebSocket commands, and API endpoints.
* 🗺️ **[Development Roadmap (TODOS)](docs/todos.md)**: Active task checklist and implementation gantt phases.
