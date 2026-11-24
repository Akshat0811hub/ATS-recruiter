# OrbiTouch ATS — Security & Privacy Engineering Specification

## 1. Enterprise Security Controls Implemented

1. **Transport & Session Hardening:**
   - Encrypted database-backed sessions (`SESSION_ENCRYPT=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`).
   - Security response headers enforced on every web response via [`EnsureActiveUser`](file:///c:/Users/D552266/Desktop/ATS/app/Http/Middleware/EnsureActiveUser.php):
     - `X-Frame-Options: SAMEORIGIN`
     - `X-Content-Type-Options: nosniff`
     - `Referrer-Policy: strict-origin-when-cross-origin`
     - `X-XSS-Protection: 1; mode=block`
   - Automatic CSRF verification on all state-mutating POST/PUT/DELETE requests.

2. **Authentication, Rate Limiting & Two-Factor Authentication (2FA):**
   - Passwords hashed with Bcrypt (`BCRYPT_ROUNDS=12`).
   - Login rate limiting (`RateLimiter::tooManyAttempts`, 5 attempts per email/IP window) and automatic 15-minute account lock (`locked_until`) after 5 consecutive failed password attempts.
   - Complete login activity ledger (`login_activities`) tracking `login_success`, `login_failed`, `login_rate_limited`, `2fa_verified`, `session_revoked`, and `logout`.
   - RFC-6238 TOTP Two-Factor Authentication + 8 single-use recovery codes via [`TwoFactorService`](file:///c:/Users/D552266/Desktop/ATS/app/Services/TwoFactorService.php), mandatory for `super_admin` in production when `ATS_REQUIRE_SUPER_ADMIN_2FA=true`.
   - Immediate session revocation when an account is deactivated or when a user triggers "Revoke Other Sessions".

3. **Role-Based Access Control (RBAC) & Client Organisation Isolation:**
   - Server-side enforcement via [`CheckRoleOrPermission`](file:///c:/Users/D552266/Desktop/ATS/app/Http/Middleware/CheckRoleOrPermission.php) and [`EnsureClientIsolation`](file:///c:/Users/D552266/Desktop/ATS/app/Http/Middleware/EnsureClientIsolation.php).
   - Client HR users are bound to `client_id` and restricted strictly to `/portal/*` and their own organisation's records.
   - Internal notes (`is_client_visible = false`), recruiter margins, placement fees, other clients, and unsubmitted master candidates are never exposed to external client accounts.

4. **Private Document Vault & Upload Protection:**
   - All resumes, job descriptions, client agreements, offer letters, and joining documents are stored in `storage/app/private/vault/...` outside `public/`.
   - Strict extension whitelist (`pdf`, `doc`, `docx`, `rtf`, `txt`, `png`, `jpg`, `jpeg`, `csv`, `xlsx`), MIME-type verification, and embedded PHP payload rejection (`<?php` signature inspection) in [`StorageManagementService`](file:///c:/Users/D552266/Desktop/ATS/app/Services/StorageManagementService.php).
   - Authenticated downloads via [`DocumentController@download`](file:///c:/Users/D552266/Desktop/ATS/app/Http/Controllers/DocumentController.php) verify role and tenant ownership before streaming files and record an entry in `audit_logs`.

5. **Spreadsheet Formula Injection & SQL Injection Prevention:**
   - All database queries use parameterized Eloquent/PDO bindings.
   - All CSV/Excel exports sanitize leading `=`, `+`, `-`, `@`, `\t`, `\r` characters via `ExportImportService::sanitizeCell()`.

---

## 2. Privacy & Indian DPDP Act Alignment

- **Explicit Candidate Consent Tracking:** Every candidate record tracks `consent_status` (`granted`, `pending`, `withdrawn`, `erasure_requested`) and `consent_recorded_at`.
- **Configurable Retention Policy:** Administrators configure `dpdp_retention_months` (default 36 months) and `privacy_notice_text` in System Settings.
- **Redacted Audit Logs:** [`AuditService`](file:///c:/Users/D552266/Desktop/ATS/app/Services/AuditService.php) automatically redacts `password`, `two_factor_secret`, `two_factor_recovery_codes`, `remember_token`, and `invitation_token` before persisting audit records.
- **Legal Disclaimer:** These technical privacy controls assist OrbiTouch HR & Recruitment Consultants (Orbitouch Outsourcing Private Limited) in operationalizing data protection requirements under the Indian Digital Personal Data Protection (DPDP) Act and international recruitment norms; organizational legal compliance remains subject to company policies and legal counsel review.
