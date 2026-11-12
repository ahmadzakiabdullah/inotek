# INOTEK System Changelog & Versioning Guide

This document maintains the version history of the **INOTEK** system and outlines the Semantic Versioning (SemVer) standards applied to track progress and milestones.

---

## 1. Versioning Standard (SemVer)

INOTEK uses **Semantic Versioning 2.0.0** (`vMajor.Minor.Patch`):
* **Major (X.0.0)**: Significant structural or architectural changes (e.g., database schema overhaul, multi-tenant updates, or a complete design rewrite).
* **Minor (0.Y.0)**: New features or portals added (e.g., adding the Certificate Verification system, real-time Reverb integrations, or the Judge Evaluation module).
* **Patch (0.0.Z)**: Bug fixes, minor visual adjustments, sidebar re-ordering, or configuration clear-ups.

---

## 2. INOTEK Version History

### [v1.7.0] - 2026-11-12 (Current Release)
#### Security
* **Certificate Verification Privacy**: The public verify page now receives only names, project title/code, category and session. Student, team member and supervisor emails and phone numbers are no longer exposed.
* **Results Confidentiality**: Award levels, scores and rankings stay hidden on the public leaderboard, home page and verify page until Round 2 results are locked. Achievement certificates cannot be downloaded before then (admins excepted).
* **Lecturer and Participant Visibility**: Lecturers see scores, awards and judge comments on the staff leaderboard only after results are locked; participant and supervisor pages no longer receive the award level early. Admins and judges keep full access.
* **Project Registration**: Only participant accounts can register projects. Nobody can approve, return or cancel their own project.
* **Judge Conflict of Interest**: A judge who owns or supervises a project cannot be assigned to evaluate it.
* **Hardening**: Student list limited to admins, roles protected by name, internal error messages hidden, stricter score and project validation.
#### Changed
* **Round 1 Lock**: Round 1 scores are frozen automatically once any Round 2 assignment exists. Delete the Round 2 assignments to reopen Round 1.
* **Leaderboard Ordering**: Round 2 finalists always rank above projects scored only in Round 1.
* **Safe Deletion**: Sessions, categories and roles that still have projects or users cannot be deleted.
* **Results Lock**: `r2_locked` is no longer editable from the session form; it changes only through the audited Round 2 lock toggle.
* **Certificate QR Codes**: Generated locally instead of through an external service.
#### Fixed
* **Migrations**: `2026_11_02_000001_scope_rubric_mappings_to_sessions` no longer fails on fresh MySQL databases (error 1553).
* **Changelog Rendering**: Changelog content is escaped before rendering.

### [v1.6.3] - 2026-10-23
#### Changed
* **Sidebar Contact Card**: Replaced the Download promotion with faculty contact information and a Secretariat button linking to the INOTEK contact page.
* **Project Session Attribution**: Existing project registrations have been assigned to Sesi 1/2026 while Sesi 2/2026 remains the active session for new operations. Session-specific dashboards and approval filters show the selected session.
* **Shared Category Catalog**: Categories and their rubric mappings are reused across sessions; project codes restart within each session.
* **Session Scheduling**: Session period and registration open/close dates are stored as dates, with a registration mode controlling open, closed, or scheduled registration.
#### Fixed
* **Changelog Cache Action**: The dashboard route is `/dashboard/changelog/clear-cache`; a compatibility route also supports older bundles posting to `/changelog/clear-cache`.
* **PHP CLI Setup**: The local Windows PATH now resolves PHP 8.4.27 by default.

### [v1.6.2] - 2026-10-23
#### Fixed
* **User Role Assignment**: New registrations are assigned the `User` role, role summaries use role names instead of fixed numeric IDs, and legacy accounts can be mapped using project ownership, scoring activity, and confirmed institution email domains.
* **Legacy User Updates**: User profile and role updates no longer expect `created_at` or `updated_at` columns in the legacy users table.
* **User Editing on Restricted Hosts**: The edit form uses Laravel method spoofing so hosting environments that block direct `PUT` requests can still process user updates.

### [v1.6.1] - 2026-10-23
#### Fixed
* **Session Approval Filtering**: Project approval lists and summary cards now follow the selected session, defaulting to the active session.
* **Filter Control Layout**: Select controls now stay within their allocated width, preventing long session labels from overlapping adjacent filters.
* **Session Date Display**: Session and registration periods show concise `dd/mm/yyyy` dates instead of full UTC timestamps.

### [v1.6.0] - 2026-10-23
#### Changed
* **Shared Categories Across Sessions**: Categories are now defined once and reused by every competition session, instead of creating duplicate category records per session.
* **Category Management**: Removed session assignment from category setup. Categories used by existing projects and rubric mappings are consolidated by category code and remain linked to their data.

### [v1.5.0] - 2026-06-18
#### Added
* **Global System Settings Panel**: Built a dedicated administration panel to dynamically configure system names, fonts (from Google Fonts list), primary theme colors, date formats, and timezones in real-time.
* **System Administration Navigation**: Grouped System Settings, Audit Logs, and Broadcast Alerts under a new "System Administration" menu with a settings gear icon.
* **Interactive Best Presenter Nomination**: Replaced the manual text-input field with Yes/No buttons, supporting automatic creator nomination for single-student projects and a dropdown selection list for multi-member projects.

---

### [v1.4.1] - 2026-06-18
#### Added
* **Reorganized Sidebar Navigation**: Structured the Admin Portal based on logical workflows (Setup → Access → Judging → Systems & Logs).
* **Dedicated Settings Group**: Shifted Profile & Settings menus to a consolidated, distinct bottom section in the sidebar.

---

### [v1.4.0] - 2026-06-18
#### Added
* **Broadcast Announcement**: Added the Admin Composer at `/dashboard/announcements` with live previews.
* **Notification System**: Added database notifications delivered through background queues, with broadcast-ready events (Laravel Reverb not yet enabled).

---

### [v1.3.0] - 2026-06-10
#### Added
* **Security & Verification**: Integrated Passkeys support (`@laravel/passkeys`) and Two-Factor Authentication (2FA).
* **Auditing**: Added the system-wide Audit Logs dashboard to track admin actions.
* **Judge Reminder (Nudge)**: Implemented judge nudge buttons to notify judges who have not submitted scores.

---

### [v1.2.0] - 2026-05-25
#### Added
* **Certificate & Verification**: Generated downloadable PDF participation and achievement certificates for student projects.
* **Public QR Verification**: Built a public verification search/lookup endpoint mapped to certificate QR codes (`/verify/certificate/{hash}`).

---

### [v1.1.0] - 2026-05-10
#### Added
* **Judging Workspace**: Created rubrics management, category parameters, and project-to-judge mapping tools.
* **Judge Dashboard**: Setup the evaluation panel for judges to grade submissions using specific rubrics.

---

### [v1.0.0] - 2026-04-15
#### Added
* **Core Launch**: User registration, login, and profile builder.
* **Participant Workspace**: Student submission module for project details, members, media links, and files.
* **Supervision Workspace**: Lecturer/Supervisor panel for project overview and endorsement status.
