# OrbiTouch ATS — Enterprise Recruitment CRM & Applicant Tracking System

**Product:** OrbiTouch ATS  
**Organisation:** OrbiTouch HR & Recruitment Consultants  
**Legal Entity:** Orbitouch Outsourcing Private Limited  
**Corporate Website:** https://www.orbitouch-hr.com/  
**Production CRM Target:** https://crm.orbitouch-hr.com/

---

## 1. Overview

**OrbiTouch ATS** is a full-stack, production-grade Enterprise Recruitment CRM and Applicant Tracking System engineered to manage the complete recruitment lifecycle from Client Requirement to Placement Closure on **Hostinger Premium Web Hosting** (1 CPU Core, 2048 MB RAM, 20 GB NVMe storage, 40 PHP workers).

### Complete Lifecycle Managed
`Client Requirement` → `Job Description` → `Recruiter Assignment` → `Candidate Sourcing & Dedup` → `Calling` → `Screening` → `Profile Submission` → `Client Shortlisting` → `Interview Scheduling` → `Unlimited Multiple Interview Rounds (1..N)` → `Final Selection` → `Offer & Negotiation` → `Joining` → `Post-Joining Check-ins` → `Placement Closure & Replacement Warranty`.

---

## 2. Technology Stack

- **Backend:** PHP 8.3+, Laravel 13, Eloquent ORM, Session/2FA Authentication, Granular RBAC Policies & Middleware, Bounded Cron Queue Worker.
- **Frontend:** Laravel Blade, Livewire, Alpine.js, Tailwind CSS (Vite pre-compiled static assets — zero Node.js server required in production).
- **Database:** MySQL / MariaDB (InnoDB, UTF-8mb4, 44 normalized relational tables, composite indexes, transactional integrity, SQLite CI support).
- **Storage & Backups:** Private non-public file storage (`storage/app/private/vault/`), SHA-256 binary deduplication, 70%/85%/95% quota alerts, AES-256 encrypted daily backups with SHA-256 integrity verification.

---

## 3. Local Development & Testing Quickstart

```bash
# 1. Install PHP and Frontend dependencies
composer install
npm install
npm run build

# 2. Configure environment & database
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# 3. Run the automated 30-scenario test suite
php artisan test
```

---

## 4. Complete Documentation Index

- [Project Implementation Plan](file:///c:/Users/D552266/Desktop/ATS/PROJECT_PLAN.md)
- [System Architecture](file:///c:/Users/D552266/Desktop/ATS/ARCHITECTURE.md)
- [Database Schema & 44-Table ERD](file:///c:/Users/D552266/Desktop/ATS/DATABASE_SCHEMA.md)
- [RBAC & Permissions Matrix](file:///c:/Users/D552266/Desktop/ATS/PERMISSIONS.md)
- [API & JSON Endpoints](file:///c:/Users/D552266/Desktop/ATS/API_DOCUMENTATION.md)
- [Hostinger Production Deployment & Rollback Guide](file:///c:/Users/D552266/Desktop/ATS/DEPLOYMENT_HOSTINGER.md)
- [Security & DPDP Privacy Controls](file:///c:/Users/D552266/Desktop/ATS/SECURITY.md)
- [Encrypted Backup & Restoration Procedure](file:///c:/Users/D552266/Desktop/ATS/BACKUP_AND_RESTORE.md)
- [Automated QA & Performance Test Report](file:///c:/Users/D552266/Desktop/ATS/TEST_REPORT.md)
- [Recruiter & Client Portal User Guide](file:///c:/Users/D552266/Desktop/ATS/USER_GUIDE.md)
- [Super Admin & Manager Guide](file:///c:/Users/D552266/Desktop/ATS/ADMIN_GUIDE.md)
- [Development Progress Tracker](file:///c:/Users/D552266/Desktop/ATS/DEVELOPMENT_PROGRESS.md)
- [Release Changelog](file:///c:/Users/D552266/Desktop/ATS/CHANGELOG.md)
