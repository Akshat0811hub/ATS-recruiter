# OrbiTouch ATS — Encrypted Daily Backup & Restoration Procedure

## 1. Backup Architecture

Hostinger Premium Web Hosting provides weekly native server backups (Singapore datacenter), while OrbiTouch ATS implements a dedicated **Daily Encrypted Backup Engine** ([`BackupService`](file:///c:/Users/D552266/Desktop/ATS/app/Services/BackupService.php)) scheduled at **02:00 AM IST** via `php artisan ats:backup --type=full`.

### What is Backed Up
1. **Complete Relational Database (AllCore Recruitment Tables):** Organisations, Roles, Permissions, Teams, Storage Records, Clients, Contacts, Job Requirements, Assignments, Screening Questions, Interview Round Definitions, Candidates, Contacts, Skills, Resume Versions, Documents, Notes, Applications, Screenings, Call Logs, Communications, Client Submissions, Client Feedback, Interview Records, Attempts, Feedback, Offers, Revisions, Joinings, Placements, Replacements, Tasks, Email Templates, Settings, and Custom Fields.
2. **Private Document Vault Manifest & Payloads:** Every private file (`resume`, `jd`, `agreement`, `offer_letter`, `joining_doc`) with its SHA-256 checksum and encrypted binary snapshot.
3. **Encryption & Integrity Verification:**
   - Encrypted using Laravel's `APP_KEY` AES-256-CBC cipher (`Crypt::encryptString`).
   - Verified with a SHA-256 checksum stored in `backup_logs.sha256_checksum`.

---

## 2. External Off-Server Storage Configuration

To prevent filling the 20 GB Hostinger NVMe hosting account with repeated full backups:
1. Configure an approved external destination in `.env`:
   ```env
   ATS_BACKUP_ENABLED=true
   ATS_BACKUP_EXTERNAL_DISK=s3
   ATS_BACKUP_RETENTION_DAYS=30
   ```
2. Until `ATS_BACKUP_EXTERNAL_DISK` is populated in `.env`, the Super Admin Backup Management dashboard (`/admin/backups`) and System Health dashboard (`/admin/system-health`) display an explicit readiness banner indicating that external off-server backup destination configuration is pending.

---

## 3. Verified Restoration Procedure

### Option A: One-Click Super Admin UI Restoration
1. Sign in as Super Admin (`super_admin`) with verified 2FA.
2. Navigate to **Administration → Backup Management** (`/admin/backups`).
3. Locate the desired backup (`BKP-YYYYMMDD-HHMMSS-XXXX`) and click **Verify SHA-256 & Restore**.
4. `BackupService::verifyAndRestoreBackup()` validates the SHA-256 checksum against tampering or corruption, decrypts the AES-256 payload inside a database transaction, restores missing or soft-deleted records without overwriting newer active records, and restores any missing private files in `storage/app/private/vault/`.

### Option B: Command-Line / Disaster Recovery Restoration
1. Provision the application repository and `.env` with the original `APP_KEY`.
2. Run database migrations:
   ```bash
   php artisan migrate --force
   ```
3. Restore via Tinker or the Backup Management dashboard:
   ```bash
   php artisan tinker --execute="app(\App\Services\BackupService::class)->verifyAndRestoreBackup(\App\Models\BackupLog::latest()->first());"
   ```
