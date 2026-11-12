# Product Requirements Document (PRD): INOTEK

This document outlines the core functional requirements, user personas, operational rules, and workflow state machines for the INOTEK competition portal.

---

## 1. User Personas & Permissions

The system uses four database-backed roles with distinct access rights. All newly registered accounts receive the `user` role by default. Role meaning comes from the role name, not a fixed numeric ID.

| Role | Access Level & Description | Key Capabilities |
| :--- | :--- | :--- |
| **Participant** | Student / Inventor Account | - Register a project submission (only participants can register projects).<br>- Manage team members (stored in dynamic records).<br>- Update, submit or delete the project only while it is a draft (`NEW`/`EDIT`) and the session registration window is open.<br>- Download certificates from the My Project page once available. |
| **Lecturer** | UTeM staff / supervisor (reviewer) | - Review projects that list them as supervisor (matched by `supervisor_email`).<br>- Approve, reject (return for editing) or cancel submitted projects they supervise.<br>- View the staff leaderboard; scores, awards and judge comments appear only after results are finalized (`r2_locked`).<br>- Cannot register projects or download certificates. |
| **Judge** | Evaluator Panelist | - View assigned projects scoped by the active session.<br>- Complete dynamic scoring rubrics per project category.<br>- Nominate a "Best Presenter" (a field on the evaluation form) and leave feedback comments. |
| **Admin** | System Administrator | - Manage users, roles, categories, sessions, rubrics and system settings.<br>- Bulk approve/reject/cancel incoming project registrations and register projects for participants.<br>- Assign projects to judges (Round 1 & Round 2).<br>- Setup Round 2 shortlist size, awards and lock states.<br>- Send broadcast alerts and nudge pending judges.<br>- View audit logs. |

Nobody can review their own project: approval actions always exclude projects owned by the reviewing user.

---

## 2. Core Functional Requirements

### 2.1 Semester-Based Isolation
> [!IMPORTANT]
> The system must isolate operational competition data by session.
- Projects, scores, and judge assignments must be scoped by `session_id`; dashboards and approvals default to the active session and allow the administrator to select another session where applicable.
- The same project IDs and judge IDs can reappear in different sessions without database constraint conflicts.
- A session stores overall start/end dates, registration start/end dates and a `registration_mode` (`scheduled`, `open` or `closed`):
  - `scheduled`: registration is open only between the registration start and end dates.
  - `open` / `closed`: overrides the registration dates and forces registration open or closed.
  - In every mode, registration is closed before the session start date and after the session end date.
- Categories are a shared global catalog reused in every session. Rubrics and judge accounts may also be reused, but the category → rubric mapping is chosen per session; project and evaluation records remain session-specific.

### 2.2 Project & Team Registration
- **Rules**:
  - A participant can register a project only if they do not already own a project in the active session.
  - The project code (`pcode`) must be unique within the active session.
  - Project code numbering restarts for each session and does not continue from the previous session.
  - Dynamic team member lists must be stored in a normalized database table (`penyelidik` mapped to `TeamMember`), replacing legacy flat columns.
- **Validation**:
  - Selection of a category (from the dynamically managed shared catalog). Team members are accepted only when the category's `allow_team` flag is enabled.
  - Selection of institute category (`utem` or `ipt`).
  - Optional poster URL and demo video URL fields (links validated as URLs; no file upload).

### 2.3 Administrative Verification
Projects progress through a defined state machine:
```
Participant creates ──> [STATUS_NEW] (1)
[STATUS_NEW] (1) / [STATUS_EDIT] (2) ──submit──> [STATUS_SUBMITTED] (3)
[STATUS_SUBMITTED] (3) ──approve──> [STATUS_APPROVED] (4)
[STATUS_SUBMITTED] (3) ──reject───> [STATUS_EDIT] (2)   (returned with comments)
[STATUS_SUBMITTED] (3) ──cancel───> [STATUS_CANCELLED] (6)
Admin registers project ──> [STATUS_APPROVED] (4)       (direct approval)
```
- Only administrators and supervising lecturers can approve, reject or cancel projects; lecturers are limited to projects they supervise, and nobody can review their own project.
- Only `STATUS_SUBMITTED` projects can be approved, rejected or cancelled.
- Bulk verification actions allow reviewers to approve or cancel multiple projects simultaneously.

### 2.4 Category-Based Judging (Round 1)
- **Dynamic Rubrics**: Rubrics are managed by admins and mapped to project categories per session.
- **Evaluation**:
  - Judges grade projects against mapped rubric items; each item has its own `max_points` (1–100, default 5).
  - The final score is calculated from each rubric item's normalized weight: `(score / max_points) * (item_weight / sum_of_item_weights) * 100`. This keeps the maximum score at 100 even if configured weights do not add up to exactly 100%.
  - Aggregate scores and item-level details (in JSON format) are stored in the database.

### 2.5 Round 2 (Pusingan Ke-2) Judging
> [!WARNING]
> Round 2 introduces strict judging rules to ensure transparency.
- **Shortlisting**:
  - After Round 1 finishes, the system aggregates average scores.
  - The system automatically extracts the top projects per category (Top 3, 5 or 10, configured by the admin).
- **Assignment Rules**:
  - Round 2 assignments are allowed only for shortlisted projects and only after all Round 1 evaluations are complete.
  - Round 1 auto-locks once any Round 2 assignment exists; delete the Round 2 assignments to reopen Round 1.
- **Judging Conflict Avoidance**:
  - **Rule**: Round 2 assignments must block any judge who evaluated the same project in Round 1.
  - **Rule**: A judge who owns or supervises a project cannot be assigned to it in any round (conflict of interest).
- **Session Control**:
  - Round 2 can be locked/finalized (`r2_locked`). Once locked, Round 2 scores cannot be modified and Round 2 assignments cannot be added or removed.
  - Award assignment is not blocked by `r2_locked`.
- **Leaderboard Ordering**:
  - Projects with any Round 2 score always rank above Round 1-only projects, then by final score (Round 2 average when present, otherwise Round 1 average).

### 2.6 Results Confidentiality
> [!IMPORTANT]
> Results stay confidential until Round 2 is finalized (`r2_locked`).
- Before `r2_locked`:
  - The public leaderboard shows no scores or awards and lists projects sorted by project code.
  - Homepage rankings are empty.
  - The certificate verification page hides the award.
  - Achievement certificate downloads return 403 for everyone except admins.
  - Participants' and lecturers' pages do not receive `award_level`.
- The staff leaderboard (`/dashboard/leaderboard`) shows full scores, awards and judge comments to admins and judges at any time, and to lecturers only after `r2_locked`.

### 2.7 Dynamic Certificate Generation
- **Participation Certificate**: Generated for all approved projects.
- **Achievement Certificate**: Generated for projects with an award. Awards (Gold, Silver, Bronze) are chosen manually by the admin, only for shortlisted projects that have a Round 2 score; they are not computed from score brackets.
- **Public Verification (QR)**:
  - Each certificate features a unique QR code pointing to a verification URL: `/verify/certificate/{hash}`.
  - Scanning the QR code displays the student's name, project title, project code, category, session and team member names, plus the award level only after `r2_locked`. No score is shown.

---

## 3. Non-Functional Requirements

- **Security**: Strict session checks, CSRF validation, and parameterized SQL queries to prevent SQL injection.
- **Data Safety**: Use of Soft Deletes on Projects, Team Members, and User accounts to prevent accidental loss of historical records.
- **Real-Time Responsiveness** *(planned)*: WebSockets for live leaderboard scoring updates during the event. Not live yet (`BROADCAST_CONNECTION=log`, Reverb not installed); the leaderboard currently polls `?api=true`.
- **Language & Localization**: All user interfaces, pages, labels, navigation items, buttons, alerts, and system-level response messages must be written exclusively in **English**.

---

## 4. Role-Based Navigation & UI

Each user role has a tailored navigation menu and dashboard specific to their tasks and access privileges.

### 4.1 Admin Menu & Views
- **Access Level**: Full Administrative Access
- **Dashboard Widgets**:
  - Quick statistics (Total projects, total participants, total judges).
  - Scoring progress breakdown by categories.
  - Active session indicator.
- **Sidebar Menu Items**:
  - Platform:
    - `Dashboard`: General overview.
    - `Live Leaderboard`: Real-time rankings with full scores, awards and judge comments.
  - Competition Setup:
    - `Sessions Management`: Manage active competition session/semester.
    - `Categories Management`: CRUD dynamic categories.
    - `Rubrics Management`: CRUD rubrics, rubric items, and per-session category-rubric mapping.
  - User & Access Admin:
    - `Users Management`: CRUD actions for users.
    - `Roles Management`: View and update system roles.
  - Judging Workspace:
    - `Project Approvals`: Multi-select project approval and comment panel.
    - `Judge Assignments`: Assign Round 1 & Round 2 judges to projects.
    - `Round 2 Shortlist`: Shortlist size, Round 2 results, awards and lock toggle.
  - System Administration:
    - `System Settings`: Application settings.
    - `System Audit Logs`: Activity logger view.
    - `Broadcast Alerts`: Send manual announcements.
  - Results export *(planned)*: not implemented yet.

### 4.2 Lecturer Menu & Views
- **Access Level**: Supervisor / Reviewer
- **Dashboard Widgets**:
  - Supervised projects summary.
  - Verification statuses (New, Submitted, Approved, Cancelled).
- **Sidebar Menu Items**:
  - `Dashboard`: My dashboard.
  - `Live Leaderboard`: Rankings; scores, awards and judge comments appear only after `r2_locked`.
  - `Project Approvals`: Approve, reject or cancel submitted projects where the lecturer is the supervisor.

### 4.3 Judge Menu & Views
- **Access Level**: Evaluator Panelist
- **Dashboard Widgets**:
  - Appraisal progress bar (e.g. "4 / 10 Projects Evaluated").
  - Shortcuts to pending evaluations.
- **Sidebar Menu Items**:
  - `Dashboard`: Judge statistics & summary.
  - `Live Leaderboard`: Real-time rankings.
  - `Evaluation Panel`: Interactive scorecard list of assigned projects. The evaluation form includes the "Best Presenter" field.

### 4.4 User (Student / Participant) Menu & Views
- **Access Level**: Participant / Student (Inventor perspective)
- **Dashboard Widgets**:
  - Registered project overview.
  - Submission status tracking timeline.
  - Certificates availability.
- **Sidebar Menu Items**:
  - `Dashboard`: Participant dashboard.
  - `My Project Submission`: Single project submission form (team member listing, video URL, poster URL) with participation/achievement certificate download buttons once available.
