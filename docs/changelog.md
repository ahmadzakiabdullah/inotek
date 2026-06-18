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

### [v1.5.0] - 2026-06-18 (Current Release)
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
* **Real-time Broadcast Announcement**: Added the Admin Composer at `/admin/announcements` with live previews.
* **Notification System**: Integrated real-time popup alerts via Laravel Reverb (WebSockets) and asynchronous background queues for database notification delivery.

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
