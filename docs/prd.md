# Product Requirements Document (PRD): INOTEK

This document outlines the core functional requirements, user personas, operational rules, and workflow state machines for the INOTEK competition portal.

---

## 1. User Personas & Permissions

The system supports three primary user roles with distinct access rights:

| Role | Access Level & Description | Key Capabilities |
| :--- | :--- | :--- |
| **Participant** | Student / Inventor Account | - Register a project submission.<br>- Manage team members (stored in dynamic records).<br>- Update project metadata (only prior to final submission).<br>- Download certificates post-event. |
| **Judge** | Evaluator Panelist | - View assigned projects scoped by the active session.<br>- Complete dynamic scoring rubrics per project category.<br>- Vote for "Best Presenter" and leave feedback comments. |
| **Admin** | System Administrator | - Manage users, roles, categories, sessions, and rubrics.<br>- Bulk approve/reject incoming project registrations.<br>- Assign projects to judges (Round 1 & Round 2).<br>- Setup Round 2 qualifiers, toggles, and lock states.<br>- View audit logs. |

---

## 2. Core Functional Requirements

### 2.1 Semester-Based Isolation
> [!IMPORTANT]
> The system must isolate all operational data by competition sessions (semesters).
- All projects, team members, scoring records, and judging assignments must be explicitly scoped by an active `session_id`.
- The same project IDs and judge IDs can reappear in different sessions without database constraint conflicts.

### 2.2 Project & Team Registration
- **Rules**:
  - A participant can register a project only if they do not already own a project in the active session.
  - The project code (`pcode`) must be unique within the active session.
  - Dynamic team member lists must be stored in a normalized database table (`penyelidik` mapped to `TeamMember`), replacing legacy flat columns.
- **Validation**:
  - Selection of an active category (from a dynamically managed database list).
  - Selection of institute category (`utem` or `ipt`).
  - Validation of mandatory file links (poster URL, demo video URL).

### 2.3 Administrative Verification
Projects progress through a defined state machine:
```
[STATUS_NEW] (1) ──> [STATUS_EDIT] (2) ──> [STATUS_SUBMITTED] (3) ──> [STATUS_APPROVED] (4) ──> [STATUS_CANCELLED] (6)
```
- Only administrators can change status to `STATUS_APPROVED` or `STATUS_CANCELLED`.
- Bulk verification actions allow admins to approve/reject multiple projects simultaneously.

### 2.4 Category-Based Judging (Round 1)
- **Dynamic Rubrics**: Rubrics are managed by admins and mapped to project categories.
- **Evaluation**:
  - Judges grade projects against mapped rubric items (on a scale of 0 to 5).
  - The final score is calculated using the weighted formula: `(score / 5) * weight` for each item.
  - Aggregate scores and item-level details (in JSON format) are stored in the database.

### 2.5 Round 2 (Pusingan Ke-2) Judging
> [!WARNING]
> Round 2 introduces strict judging rules to ensure transparency.
- **Shortlisting**:
  - After Round 1 finishes, the system aggregates average scores.
  - The system automatically extracts the top projects per category (Top 3 or Top 5, dynamically configured by the admin).
- **Judging Conflict Avoidance**:
  - **Rule**: Round 2 assignments must block any judge who evaluated the same project in Round 1.
- **Session Control**:
  - Round 2 assignments can be locked (`r2_locked`). Once locked, no further scores can be modified.

### 2.6 Dynamic Certificate Generation
- **Participation Certificate**: Generated for all approved projects.
- **Achievement Certificate**: Generated for winners based on final score brackets (e.g., Gold, Silver, Bronze, or Winner rankings).
- **Public Verification (QR)**:
  - Each certificate features a unique QR code pointing to a verification URL: `/verify/certificate/{hash}`.
  - Scanning the QR code displays the student's name, project title, and award level, verifying authenticity directly on the official portal.

---

## 3. Non-Functional Requirements

- **Security**: Strict session checks, CSRF validation, and parameterized SQL queries to prevent SQL injection.
- **Data Safety**: Use of Soft Deletes on Projects, Team Members, and User accounts to prevent accidental loss of historical records.
- **Real-Time Responsiveness**: Use of WebSockets for live leaderboard scoring updates during the event.
- **Language & Localization**: All user interfaces, pages, labels, navigation items, buttons, alerts, and system-level response messages must be written exclusively in **English**.

---

## 4. Role-Based Navigation & UI

Each user role has a tailored navigation menu and dashboard specific to their tasks and access privileges.

### 4.1 Admin Menu & Views
- **Access Level**: Full Administrative Access
- **Dashboard Widgets**:
  - Quick statistics (Total projects, total participants, total judges).
  - Scoring progress breakdown by categories.
  - Active session indicator and toggle.
- **Sidebar Menu Items**:
  - `Dashboard`: General overview.
  - `Users Management`: CRUD actions for users.
  - `Roles Management`: View and update system roles.
  - `Sessions Management`: Manage active competition session/semester.
  - `Categories Management`: CRUD dynamic categories.
  - `Rubrics Management`: CRUD rubrics, rubric items, and category-rubric mapping.
  - `Project Approvals`: Multi-select project approval and comment panel.
  - `Judge Assignments`: Assign Round 1 & Round 2 judges to projects.
  - `Leaderboard & Results`: Real-time rankings & results export.
  - `Audit Logs`: Activity logger view.

### 4.2 Lecturer Menu & Views
- **Access Level**: Participant / Project Submitter (Supervisor perspective)
- **Dashboard Widgets**:
  - Supervised projects summary.
  - Verification statuses (New, Submitted, Approved, Cancelled).
  - Evaluator comments and scores (once published).
- **Sidebar Menu Items**:
  - `Dashboard`: My dashboard.
  - `My Supervised Projects`: Register and manage projects where the lecturer is the supervisor.
  - `Download Certificates`: Access participation/achievement certificates.

### 4.3 Judge Menu & Views
- **Access Level**: Evaluator Panelist
- **Dashboard Widgets**:
  - Appraisal progress bar (e.g. "4 / 10 Projects Evaluated").
  - Shortcuts to pending evaluations.
- **Sidebar Menu Items**:
  - `Dashboard`: Judge statistics & summary.
  - `Project Evaluations`: Interactive scorecard list of assigned projects.
  - `Voting Panel`: Cast vote for "Best Presenter".

### 4.4 User (Student / Participant) Menu & Views
- **Access Level**: Participant / Student (Inventor perspective)
- **Dashboard Widgets**:
  - Registered project overview.
  - Submission status tracking timeline.
  - Certificates availability.
- **Sidebar Menu Items**:
  - `Dashboard`: Participant dashboard.
  - `My Project Submission`: Single project submission form (team member listing, video link, poster upload).
  - `Download Certificates`: Download PDF certificate.

