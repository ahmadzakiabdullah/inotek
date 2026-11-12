# INOTEK Development Roadmap (TODOS)

This document outlines the roadmap, plans, and checklist for developing the new INOTEK system based on the legacy PHP application, gap requirements, and system refinements.

> [!NOTE]
> Status reviewed 12 November 2026. The checklist records implementation work;
> deployment-specific setup and remaining work are called out separately.

---

## 📋 Task Checklist

### 1. Database & Model Alignment (Session & Semester Isolation)
Judging runs twice a year (semesters). The same project IDs and judges can reappear in different sessions.
- [x] Configure `Project` model status constants:
  - `1` = `STATUS_NEW`
  - `2` = `STATUS_EDIT`
  - `3` = `STATUS_SUBMITTED`
  - `4` = `STATUS_APPROVED`
  - `6` = `STATUS_CANCELLED`
- [x] Ensure all operations on projects (`pendaftaran` table) and evaluations (`scores` table) are strictly scoped by `session_id`.
- [x] Create migration to add `round_no` (TINYINT, default 1) to the `scores` table.
- [x] Create migration to add `r2_locked` (BOOLEAN, default false) to the `competition_sessions` table.
- [x] Set up composite unique key index: `[project_id, judge_id, session_id, round_no]` to allow the same judge to evaluate the same project ID across different semesters or rounds without database conflict.

### 2. Shared Categories Management
Categories are dynamic but global: maintain one category catalog and reuse the same category names and codes across sessions. Project records remain session scoped.
- [x] Create `categories` database table (replacing static C1-C6 codes):
  - `id` (primary key)
  - `code` (e.g., `C1`)
  - `name` (e.g., `Green Technology`)
  - No session foreign key; categories are shared across sessions.
- [x] Link `Project` model to `Category` model via `category_id` (foreign key).
- [x] Build Admin Panel UI to CRUD categories (Add, edit, remove).

### 3. Category-Rubric Mapping
Pemarkahan (Scoring) must pull dynamic rubric criteria based on the selected project category.
- [x] Create database tables:
  - `rubrics` (id, name, description)
  - `rubric_items` (id, rubric_id, criteria_name, weight, max_points)
  - `category_rubric_mapping` (id, category_id, session_id, rubric_id) — one rubric per category per session.
- [x] Build Admin Panel to assign rubrics to categories (per session).
- [x] Build dynamic evaluation form rendering in the Judge Portal (looks up project's category -> looks up rubric -> renders inputs dynamically).
- [x] Store breakdown scores as a JSON array (`score_details`) in the `scores` table, and final calculated value in `total`.

### 4. Round 2 (Pusingan Ke-2) Pemarkahan
Select Top 3, 5 or 10 projects per category for a second evaluation round, ensuring no judge conflict.
- [x] Develop query logic in `ScoreCalculator` to average Round 1 scores:
  - Select Top N projects (where N is configurable: 3, 5 or 10) grouped by `category_id`.
- [x] Build Admin UI to view the qualified Round 2 shortlist and assign Round 2 judges.
- [x] Implement validation rule: Block assignment if the judge already evaluated the same project in Round 1.
- [x] Implement Round 2 locking (`r2_locked` inside the active session).
- [x] Auto-lock Round 1 once any Round 2 assignment exists (delete Round 2 assignments to reopen).
- [x] Allow Round 2 assignments only after all Round 1 evaluations are complete (and only for shortlisted projects).
- [x] Block conflict-of-interest assignments: a judge who owns or supervises a project cannot be assigned to it.
- [x] Keep results confidential before `r2_locked`: no scores/awards on the public leaderboard, empty homepage rankings, award hidden on the verify page, achievement certificates admin-only, and no `award_level` sent to participants' or lecturers' pages (lecturers see staff leaderboard scores only after `r2_locked`).

### 5. Project Registration & Team Management
- [x] Design the dynamic team member registration form in React.
- [x] Set up the `TeamMember` model and associate it with the `Project` model (One-to-Many relationship mapping to the `penyelidik` database table).
- [x] Add optional poster URL and video URL fields (validated links, no file upload).

### 6. Admin Approvals & Project Verification
- [x] Implement bulk project approvals (`approve_projects`) interface for admins.
- [x] Add administrative review comments and flags for rejected or pending projects.

### 7. Certificate Generation
- [x] Configure **DomPDF** template wrappers in the backend.
- [x] Build user-facing download routes for "Participation" and "Achievement" certificates.

### 8. Real-Time Leaderboard & Audit Log
- [ ] Set up **Laravel Reverb** (WebSocket) to stream live score changes to the public leaderboard. The app already runs on Laravel 13, `ScoreUpdated` implements `ShouldBroadcast`, and the leaderboard listens on `window.Echo`; only the Reverb server/config is missing (`BROADCAST_CONNECTION=log`), so the leaderboard currently polls `?api=true`.
- [x] Set up automatic event listeners to log critical admin actions (resets, status changes, rubric edits) to the `audit_logs` table.

### 9. Deployment Setup & Hardening (Remaining Work)
- [ ] Configure SMTP mail delivery and run a queue worker (mail currently uses `MAIL_MAILER=log`).
- [ ] Enforce email verification (`MustVerifyEmail` on the `User` model).
- [ ] Switch critical foreign keys to `restrictOnDelete` to protect historical evaluation records.
- [ ] Store `rubric_id` on `scores` so each evaluation records the rubric it was graded against.
- [ ] Move project authorization into a `ProjectPolicy`.
- [ ] Replace the project status integer constants with a status enum.
- [ ] Update the 16 stale feature tests to match current behaviour.
- [ ] Document/remove the MySQL requirement for running the test suite (tests currently require MySQL).

---

## 🛠️ Implementation Phases

```mermaid
gantt
    title INOTEK Migration & Development Phases
    dateFormat  YYYY-MM-DD
    section Phase 1: Foundation & Db
    Database & Models Alignments  :active, des1, 2026-06-18, 5d
    Categories & Rubric Tables     :         des2, after des1, 5d
    section Phase 2: Core Flows
    Registration & Team CRUD       :         des3, after des2, 7d
    Admin Approvals & Verification :         des4, after des3, 4d
    section Phase 3: Judging & R2
    Judging Portal & Dynamic Form  :         des5, after des4, 7d
    Round 2 Shortlist & Safeguards :         des6, after des5, 6d
    section Phase 4: Extras & GoLive
    PDF Certificates & Leaderboard :         des7, after des6, 5d
    QA Testing & Deployment        :         des8, after des7, 4d
```

### Phase 1: Foundation & Db (10 Days)
Align models, setup dynamic categories and category-rubric mapping tables, and seed roles.

### Phase 2: Core Flows (11 Days)
Build the registration form and bulk project approval/verification UI.

### Phase 3: Judging & Round 2 (13 Days)
Develop the dynamic rubric scoring, the judge scoring dashboard, and the Round 2 qualification process.

### Phase 4: Extras & Go-Live (9 Days)
Add DomPDF certificate exports, real-time leaderboard broadcasts, comprehensive feature tests, and deploy the system.

---

## 🚀 Advanced Polish & Recommendations (Value-Add Features)

These are recommended high-premium enhancements to improve user engagement, system reliability, and institutional security:

### 1. Dynamic Certificate Verification (Secure QR Code Verification)
- [x] Add a unique, secure QR code at the bottom of generated PDF certificates (DomPDF).
- [x] Implement a public verification route: `/verify/certificate/{hash}`.
- [x] Scan verification: Allows external parties (employers, sponsors) to scan the certificate QR and instantly verify the student's name, project title, project code, category, session and team members on the system's official portal. No score is shown; the achievement level appears only after `r2_locked`.

### 2. Gamified Judge Progress Dashboard
- [x] Build a visual progress tracker for judges showing:
  - "Total Assigned Projects" vs. "Completed Appraisals".
  - A clean visual progress bar to motivate judges.
  - Alert indicators for projects pending scoring.

### 3. Real-Time Admin Judging Monitor (Visual Chart)
- [x] Add a real-time completion chart in the Admin Dashboard showing progress per category (e.g., Green Tech: 90% judged, emerging tech: 40% judged).
- [x] Implement a single-click "Nudge Pending Judges" button to send automated friendly email reminders to judges who haven't completed their scoring. Emails are sent synchronously (not queued), and nothing is delivered while `MAIL_MAILER=log`; delivery depends on SMTP setup.

### 4. Database Integrity & Soft Deletes
- [x] Implement Laravel's `SoftDeletes` trait on the `Project`, `TeamMember`, and `User` models.
- [x] Prevent data loss: If a user or project is deleted accidentally, admins can restore it from the backend instead of corrupting historical evaluation records.

### 5. Admin Audit Log Viewer
- [x] Create a read-only logging dashboard at `/admin/audit-logs` mapping system actions.
- [x] Filter actions (e.g., "Reset Data", "Category Added", "Project Approved") showing timestamp, IP address, and the user who executed the action.
