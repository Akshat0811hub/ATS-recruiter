# OrbiTouch ATS — Super Admin & Manager Administrator Guide

## 1. Executive Oversight Without Manual Follow-ups

The **Executive Overview Dashboard** (`/dashboard`) provides live database-driven visibility across the entire OrbiTouch HR operation:
- **18 Live KPI Counters:** Active/Total Clients, Active/Overdue Requirements, Open/Filled Vacancies, Master Candidates, New Candidates Today, Calls Today, Submissions Today, Shortlists, Pending Feedback, Interviews Today, Selections, Offers Released/Accepted, Upcoming Joinings, Confirmed Joinings, and Pending Follow-ups.
- **Clickable Drilldowns:** Every metric card and lifecycle funnel bar opens the underlying filtered records directly.
- **Documented Conversion Ratios:** Submission→Shortlist, Shortlist→Interview, Interview→Selection, Offer Acceptance Ratio, and Offer→Joining with explicit formulas displayed inline.

---

## 2. Employee, Role & Team Administration

1. **User Management (`/admin/users`):**
   - Public registration is disabled. Only the Super Admin can create or invite employee and client portal accounts.
   - Deactivating a user immediately revokes all of their active sessions and blocks login.
2. **Roles & Granular Permissions (`/admin/roles`):**
   - Customise the 34 granular permissions across `Manager`, `Team Leader`, `Recruiter`, and `Client HR` roles.
3. **Team Management (`/admin/teams`):**
   - Organize recruiters into specialized practice squads with dedicated Managers, Team Leaders, and daily sourcing/call/submission targets.

---

## 3. Authorised Overrides, Candidate Merging & Replacement Vacancies

1. **Merging Duplicate Candidates:**
   - When a duplicate candidate is flagged on `/candidates/{id}`, Super Admins and Managers can click **Merge into Master Profile** to consolidate applications, call history, notes, and resume versions without data loss.
2. **Overriding a Failed Interview Round:**
   - Regular recruiters cannot progress a candidate who failed an earlier interview round. Super Admins and Managers can apply an **Authorised Override** on `/interviews` with a mandatory audit reason (minimum 10 characters), which is permanently recorded in `audit_logs`.
3. **Opening Replacement Vacancies:**
   - If a placed candidate exits within the client's replacement warranty window (e.g., 90 days), Super Admins and Managers can click **Open Replacement Vacancy** on `/joinings` to log the `ReplacementRecord` and atomically increment the job's remaining vacancy count.

---

## 4. Storage, Encrypted Backups, Data Recovery & System Health

1. **Storage Management (`/admin/storage`):**
   - Monitor the 19.51 GB Hostinger NVMe storage quota broken down by Resumes, JDs, Offer Letters, Agreements, and MySQL database usage, with automatic alerts at **70%**, **85%**, and **95%** utilisation.
2. **Encrypted Daily Backups (`/admin/backups`):**
   - Trigger on-demand AES-256 encrypted backups or verify SHA-256 integrity and restore missing records/files with one click.
3. **Soft-Deleted Data Recovery (`/admin/recovery`):**
   - Inspect and restore soft-deleted candidates, jobs, or clients.
4. **System Health (`/admin/system-health`):**
   - Inspect PHP/Laravel runtime version, database connectivity, queue/failed jobs, SMTP configuration, and external backup readiness.
