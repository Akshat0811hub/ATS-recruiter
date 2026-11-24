# OrbiTouch ATS — Enterprise Recruitment CRM & Applicant Tracking System
## Project Implementation Plan

**Product:** OrbiTouch ATS  
**Organisation:** OrbiTouch HR & Recruitment Consultants (Orbitouch Outsourcing Private Limited)  
**Target URL:** https://crm.orbitouch-hr.com/  
**Target Hosting:** Hostinger Premium Web Hosting (1 CPU Core, 2048 MB RAM, 20 GB NVMe, 40 PHP Workers, 80 Max Processes, 400,000 Inodes)  
**Timezone & Currency:** Default `Asia/Kolkata` (UTC stored in DB), Default `INR` (multi-currency supported)

---

## 1. Executive Architecture Summary

OrbiTouch ATS is engineered as a monolithic, high-efficiency Laravel + Livewire + Alpine.js + Tailwind CSS application specifically tuned for Hostinger shared hosting constraints while providing an Awwwards-calibre, Linear/Attio/Ashby-inspired enterprise SaaS experience.

- **Backend:** PHP 8.3+, Laravel, Eloquent ORM, Policies & Gates, Form Requests, Database Transactions, Private Filesystem Storage, Bounded Cron Queue Worker.
- **Frontend:** Laravel Blade, Livewire, Alpine.js, Tailwind CSS (Vite-compiled static assets, zero Node.js runtime required in production), Lucide SVG Icons, Lightweight SVG/Canvas charts.
- **Database:** MySQL / MariaDB (InnoDB, UTF-8mb4, strict foreign keys, composite indexing for 25+ concurrent recruiters, compatible with SQLite in automated CI tests).
- **Security & Compliance:** RBAC with 5 core roles (`super_admin`, `manager`, `team_leader`, `recruiter`, `client`) + granular permissions, strict Client Organisation Tenant Isolation, 2FA support (mandatory for Super Admin in production), DPDP Act privacy & consent tracking, immutable audit logs, SHA-256 file checksums, private non-public document storage.

---

## 2. Phased Delivery Roadmap

### Phase 0: Project Foundation
- Environment & Git repository initialisation (`main` branch, GitHub remote verified).
- Laravel framework setup, strict `.gitignore`, `.env.example`.
- Complete normalized relational database schema (44+ tables covering the full recruitment lifecycle).
- Core RBAC permissions matrix and design system foundation.
- Automated testing & GitHub Actions CI pipeline configuration.

### Phase 1: Authentication, RBAC & Administration
- Email/password login, password reset, rate limiting, remember me, session tracking, login activity history, failed-login monitoring.
- Two-Factor Authentication (TOTP / recovery codes, mandatory enforcement option for Super Admin in production).
- Admin-controlled user invitations, account activation/deactivation, team management.
- Configurable Business Settings (`Monday–Saturday, 10:00 AM – 6:30 PM IST`, stages, sources, priorities, custom fields, retention).

### Phase 2: Client & Job Requirement Management
- Centralised Client CRM: Unique Client ID (`CLT-YYYY-XXXX`), multiple HR contacts, fee terms, replacement period, agreement documents (private storage), live KPI counters.
- Job Requirement Engine: Auto Job ID (`JOB-YYYY-XXXX`), vacancy tracking (`openings_count`, `joined_count`, `remaining_count`), configurable interview round definitions, screening questions, JD upload, requirement cloning.
- Recruiter Assignment & Workload Management: Primary & secondary recruiters, daily sourcing/submission targets, assignment history, instant notifications.

### Phase 3: Central Candidate Database & Storage Management
- Master Candidate Database: Unique Candidate ID (`CND-YYYY-XXXX`), personal, professional, salary, notice period, consent, tags, source.
- Multi-signal Duplicate Detection: Normalized mobile number, normalized email, alternate phone, and SHA-256 resume file checksum, plus safe administrator merge workflow.
- Private Resume Management: Versioned resume uploads, MIME/extension validation, authenticated download streaming, side-panel 360-degree preview.
- Bulk CSV/Excel Import & Export with column validation, duplicate detection, and formula-injection sanitization.

### Phase 4: Calling, Screening, Submissions & Visual Pipeline
- Calling Log Engine: Click-to-call (`tel:`), call outcomes, duration, CTC/notice validation, chronological call history, automatic follow-up task generation.
- Evidence-based Screening Checklists: Per-job configurable screening criteria and overall recommendation.
- Client Profile Submissions: Immutable snapshot of candidate details & resume version at submission time, bulk submissions, duplicate submission prevention, client-visible vs internal notes separation.
- Interactive Kanban & Table Recruitment Pipeline: 13 primary lifecycle stages + 5 alternative states, drag-and-drop transitions with transactional validation and status history logging.

### Phase 5: Unlimited Interview Rounds & Dedicated Client Portal
- Unlimited Configurable Interview Rounds per job (`Round 1..N`), multiple reschedule/attempt tracking, conflict detection, mode/link/location, interviewer feedback & scoring, strict progression rules with audited admin override.
- Dedicated Client Portal: Isolated client-organisation workspace to submit requirements, review submitted profiles, compare candidates, shortlist/reject with reasons, share availability, and confirm selections/joinings.

### Phase 6: Offer Management, Joining, Placements & Replacements
- Offer Management: Offered/negotiated salary, currency, offer letter private storage, revision history, status workflow (`Selected` → `Offer Accepted` / `Backed Out`).
- Joining & Placement Closure: Expected vs actual joining, transactional vacancy counter decrement, prevent duplicate placement counting, post-joining check-in schedules (30/60/90 days), replacement window tracking & authorised vacancy reopening.

### Phase 7: Executive Dashboards, Productivity, Reports & Search
- Role-specific Dashboards: Super Admin Executive Dashboard (live funnel, conversion ratios, time-to-fill, clickable drilldowns), Manager, Team Leader, and Recruiter ("My Work") workspaces.
- Global Command Palette (`Ctrl+K`), Tasks & Follow-up engine, Notification Centre, SMTP Email Templates & Logs, 18 exportable operational reports.

### Phase 8–10: Production Reliability, Hostinger Deployment Package & QA
- Storage Management Dashboard (70% / 85% / 95% threshold alerts, orphan cleanup, 20 GB capacity protection).
- Automated Encrypted Daily Backup & Restoration engine + System Health dashboard.
- Full 30-scenario automated feature and integration test suite, Hostinger deployment scripts, cron runners, and comprehensive documentation.
