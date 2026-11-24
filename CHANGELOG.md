# Changelog — OrbiTouch ATS

All notable changes to **OrbiTouch ATS** (OrbiTouch HR & Recruitment Consultants / Orbitouch Outsourcing Private Limited) are documented in this file.

## [1.0.0] - Complete Enterprise Recruitment CRM & ATS Release (Phases 1–9)
### Added
- **Authentication, 2FA & RBAC (`Phase 1`):** Email/password authentication, login rate limiting, 5-attempt lockout, login activity history, TOTP Two-Factor Authentication + recovery codes, session revocation, controlled user invitations, 5 core roles (`super_admin`, `manager`, `team_leader`, `recruiter`, `client`) with 34 server-enforced permissions, team management, and configurable business settings (`Mon–Sat 10:00 AM – 6:30 PM IST`).
- **Client & Job Requirement Management (`Phase 2`):** Centralised Client CRM with 10 live KPIs, multiple HR contacts, private agreement uploads, Job Requirement engine with vacancy counters (`openings_count`, `joined_count`, `remaining_count`), requirement duplication without duplicating applications, and primary/secondary recruiter assignment with instant notifications.
- **Candidate Master Database & Private Storage (`Phase 3`):** 360-degree candidate profiles, multi-signal duplicate detection (normalized mobile, normalized email, alternate phone, SHA-256 resume checksum), safe administrator duplicate merge, versioned private resume storage (`ResumeVersion`), side-panel quick preview drawer, and formula-safe CSV bulk import/export.
- **Calling, Screening, Submissions & Visual Pipeline (`Phase 4`):** Chronological call discussion logger, configurable job screening checklists, immutable client profile submission snapshots, bulk profile submission, duplicate submission prevention, and interactive 13-stage Kanban + Table pipeline with optimistic locking (`409 Conflict` protection) and automated follow-up task generation.
- **Unlimited Multiple Interview Rounds & Client Portal (`Phase 5`):** Unlimited configurable interview rounds per job (`1..N`), multiple reschedule attempt tracking (`InterviewAttempt`), conflict detection, failed-round progression protection with audited admin override, and dedicated tenant-isolated Client HR Portal (`/portal/*`) with side-by-side candidate comparison.
- **Offers, Joining, Placements & Replacements (`Phase 6`):** Offer management with salary revision history (`OfferRevision`), private offer letter storage, joining confirmation with atomic vacancy recalculation and duplicate placement prevention, post-joining retention check-ins (30/60/90 days), and replacement warranty tracking with authorised vacancy reopening.
- **Executive Dashboards, Productivity & 18 Reports (`Phase 7`):** Super Admin Executive Dashboard with documented conversion formulas and clickable drilldowns, Recruiter `My Work` workspace, Team/Client performance analytics, Global Command Palette (`Ctrl+K`), Tasks & Calendar, Notification Centre, and 18 exportable operational reports.
- **Production Reliability, Backups & Hostinger Deployment Package (`Phases 8–9`):** Hostinger 20 GB storage quota dashboard with 70%/85%/95% alerts, AES-256 encrypted daily backup & SHA-256 verified restoration engine, soft-deleted record recovery vault, System Health diagnostics, 30-scenario automated PHPUnit test suite, and zero-data-loss Hostinger deployment/rollback scripts.

## [0.1.0] - Phase 0: Project Foundation & Database Architecture
### Added
- Initialised Laravel application architecture and 44 normalized relational database tables across 7 core migration files.
- Configured GitHub Actions Continuous Integration workflow (`.github/workflows/ci.yml`).
