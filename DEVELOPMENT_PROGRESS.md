# OrbiTouch ATS — Development Progress Tracker

## Current Phase
- **Active Phase:** Phases 0 through 9 Completed & Verified → Ready for Phase 10 Production Release Cutover on `https://crm.orbitouch-hr.com/`

## Completed Modules
- [x] **Phase 0: Project Foundation & Database Architecture**
  - Git & GitHub repository verified (`https://github.com/Akshat0811hub/ATS-recruiter.git`)
  - Laravel 13 + Livewire 4 + Alpine.js + Tailwind CSS + Vite foundation
  - 44 normalized relational tables with composite indexes, foreign keys, and transactional integrity
  - GitHub Actions CI pipeline (`.github/workflows/ci.yml`)
- [x] **Phase 1: Authentication, RBAC & Administration**
  - Email/password login, 5-attempt rate limiting, 15-minute lockout, login activity ledger, session revocation
  - RFC-6238 TOTP Two-Factor Authentication + recovery codes (mandatory option for Super Admin in production)
  - 5 core roles (`super_admin`, `manager`, `team_leader`, `recruiter`, `client`) + 34 server-enforced permissions
  - User Management, Controlled Invitations, Team Management, Configurable Business Settings (`Mon–Sat 10:00–18:30 IST`), Email Templates, Custom Fields
- [x] **Phase 2: Client & Job Requirement Management**
  - Centralised Client CRM (`CLT-YYYY-XXXX`), 10 live KPIs, multiple HR contacts, private agreement uploads
  - Job Requirement Engine (`JOB-YYYY-XXXX`), vacancy tracking (`openings_count`, `joined_count`, `remaining_count`), JD uploads, requirement cloning without duplicating applications, primary/secondary recruiter assignment
- [x] **Phase 3: Candidate Master Database & Private Storage Vault**
  - 360-degree candidate profiles (`CND-YYYY-XXXX`), side-panel quick preview drawer
  - Multi-signal duplicate detection (normalized mobile, normalized email, alternate phone, SHA-256 resume checksum) & safe administrator duplicate merging
  - Private versioned resume vault (`ResumeVersion`), formula-safe CSV/Excel import & export
- [x] **Phase 4: Calling, Screening, Submissions & Recruitment Pipeline**
  - Chronological call logger with click-to-call (`tel:`), job-specific screening checklists
  - Immutable client profile submission snapshots, bulk submission, duplicate submission prevention
  - Interactive 13-stage Kanban board & Table view with optimistic locking (`409 Conflict` protection) and automated follow-up task generation
- [x] **Phase 5: Unlimited Interview Rounds & Dedicated Client Portal**
  - Unlimited configurable interview rounds per job (`1..N`), reschedule attempt history (`InterviewAttempt`), conflict detection, failed-round progression block, and audited admin override
  - Tenant-isolated Client HR Portal (`/portal/*`) with requirement submission, profile shortlisting/rejection, and side-by-side candidate comparison
- [x] **Phase 6: Offer Management, Joining, Placements & Replacements**
  - Offer management with salary negotiation revision history (`OfferRevision`) and private offer letter storage
  - Joining confirmation with atomic vacancy recalculation, duplicate placement prevention, post-joining retention check-ins (30/60/90 days), and replacement vacancy reopening
- [x] **Phase 7: Executive Dashboards, Global Search, Tasks & 18 Reports**
  - Super Admin Executive Dashboard with documented conversion formulas and clickable drilldowns
  - Personalised Recruiter Workspace (`My Work`), Team/Client performance analytics, Global Command Palette (`Ctrl+K`), Calendar, Tasks, Notification Centre, and 18 exportable reports
- [x] **Phases 8 & 9: Production Reliability, Storage, Backups & Hostinger Deployment Package**
  - Hostinger 20 GB storage management dashboard with 70% / 85% / 95% threshold alerts
  - AES-256 encrypted daily backup & SHA-256 integrity-verified restoration engine + soft-deleted record recovery vault + System Health dashboard
  - Complete 30-scenario automated PHPUnit test suite + zero-data-loss Hostinger deployment (`scripts/deploy-hostinger.sh`) and rollback (`scripts/rollback-hostinger.sh`) scripts

## Pending Modules (Phase 10 Live Owner Cutover)
- [ ] Phase 10: Owner approval to configure `crm.orbitouch-hr.com` subdomain DNS on Hostinger, populate live Hostinger MySQL & SMTP credentials and external backup destination (`ATS_BACKUP_EXTERNAL_DISK`) in production `.env`, and execute `scripts/deploy-hostinger.sh`.

## Latest Git & CI Status
- **Latest Successful Commit:** `e041a28` (`test: add recruitment lifecycle integration, security and performance tests`)
- **Latest Verified GitHub Push:** `main` branch (`origin/main` at `https://github.com/Akshat0811hub/ATS-recruiter.git`)
- **Automated Test Results:** `20 passed, 0 failed (167 assertions in 3.49s)`
- **Code Formatting (Laravel Pint):** `PASSED`
- **Production Asset Build (Vite):** `PASSED (260ms)`

## Known Limitations & Blocking Issues
- Live production cutover to `https://crm.orbitouch-hr.com/` awaits owner provisioning of the Hostinger MySQL credentials, SMTP password, and external off-server backup disk (`ATS_BACKUP_EXTERNAL_DISK`) in `.env` on the Hostinger server. The application explicitly surfaces external backup readiness status on `/admin/backups` and `/admin/system-health`.

## Next Development Steps
1. When ready for live cutover on Hostinger, follow `DEPLOYMENT_HOSTINGER.md` and run `bash scripts/deploy-hostinger.sh`.
