# OrbiTouch ATS — Role-Based Access Control (RBAC) & Permissions Specification

## 1. Core Roles

| Role Slug | Display Name | Scope & Operational Responsibility |
| :--- | :--- | :--- |
| `super_admin` | Super Admin | Complete authorised system access across all clients, teams, jobs, candidates, reports, settings, backups, storage, audit logs, and user management. Mandatory 2FA in production. |
| `manager` | Manager | Operational oversight across assigned teams, client requirements, recruiter assignments, workload balancing, and team/client analytics. |
| `team_leader` | Team Leader | Manages assigned team requirements, monitors team candidates, submissions, interviews, daily sourcing targets, and follow-up completion. |
| `recruiter` | Recruiter | Works on assigned requirements, sources & updates authorised candidates, records call logs, completes screening checklists, submits profiles, schedules interviews, tracks offers & joinings, and views individual performance. |
| `client` | Client HR User | Strictly isolated to their own `client_id` organisation. Submits hiring requirements, uploads JDs, reviews submitted profiles, compares candidates, shortlists/rejects with feedback, shares interview availability, records interview feedback, and confirms selections & joinings. |

---

## 2. Granular Permissions Matrix

| Permission Key | Super Admin | Manager | Team Leader | Recruiter | Client |
| :--- | :---: | :---: | :---: | :---: | :---: |
| `dashboard.executive` | ✅ | ✅ (Scoped) | ❌ | ❌ | ❌ |
| `dashboard.team` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `dashboard.recruiter` | ✅ | ✅ | ✅ | ✅ | ❌ |
| `dashboard.client_portal` | ❌ | ❌ | ❌ | ❌ | ✅ |
| `users.manage` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `roles.manage` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `teams.manage` | ✅ | ✅ | ✅ (Own Team) | ❌ | ❌ |
| `clients.view` | ✅ | ✅ | ✅ | ✅ (Assigned) | ✅ (Own Org) |
| `clients.manage` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `jobs.view` | ✅ | ✅ | ✅ | ✅ (Assigned) | ✅ (Own Org) |
| `jobs.create` | ✅ | ✅ | ✅ | ❌ | ✅ (Own Org) |
| `jobs.update` | ✅ | ✅ | ✅ | ❌ | ✅ (Own Org) |
| `jobs.assign` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `candidates.view` | ✅ | ✅ | ✅ | ✅ | ✅ (Submitted Only) |
| `candidates.create` | ✅ | ✅ | ✅ | ✅ | ❌ |
| `candidates.update` | ✅ | ✅ | ✅ | ✅ (Authorised) | ❌ |
| `candidates.merge` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `candidates.import` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `candidates.export` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `calls.record` | ✅ | ✅ | ✅ | ✅ | ❌ |
| `screening.perform` | ✅ | ✅ | ✅ | ✅ | ❌ |
| `submissions.create` | ✅ | ✅ | ✅ | ✅ | ❌ |
| `submissions.decide` | ✅ | ✅ | ✅ | ✅ (Proxy) | ✅ (Own Org) |
| `interviews.schedule` | ✅ | ✅ | ✅ | ✅ | ✅ (Request) |
| `interviews.feedback` | ✅ | ✅ | ✅ | ✅ | ✅ (Own Org) |
| `interviews.override` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `offers.manage` | ✅ | ✅ | ✅ | ✅ | ✅ (View/Confirm) |
| `joinings.manage` | ✅ | ✅ | ✅ | ✅ | ✅ (Confirm) |
| `replacements.reopen` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `tasks.manage` | ✅ | ✅ | ✅ | ✅ (Own) | ❌ |
| `reports.view` | ✅ | ✅ | ✅ (Team) | ✅ (Own) | ✅ (Own Org) |
| `reports.export` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `audit_logs.view` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `settings.manage` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `storage.manage` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `backups.manage` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `system.health` | ✅ | ❌ | ❌ | ❌ | ❌ |

---

## 3. Server-Side Enforcement Mechanisms

1. **Middleware Layer:**
   - `EnsureActiveUser`: Immediate session termination if `is_active = false`.
   - `EnsureSuperAdminTwoFactor`: Requires verified 2FA session for `super_admin` when `ATS_REQUIRE_SUPER_ADMIN_2FA=true`.
   - `EnsureClientIsolation`: Locks `client` users to `/portal/*` and blocks access to internal staff routes; verifies `client_id` ownership on every route parameter.
   - `CheckRoleOrPermission`: Enforces role and permission slugs at the route level.
2. **Eloquent Policy Layer:**
   - `ClientPolicy`, `JobRequirementPolicy`, `CandidatePolicy`, `CandidateApplicationPolicy`, `ClientSubmissionPolicy`, `InterviewRecordPolicy`, `OfferPolicy`, `JoiningRecordPolicy`, `DocumentPolicy`.
3. **Private Document Vault Enforcement:**
   - Resumes, JDs, agreements, and offer letters are stored in `storage/app/private` and served exclusively through `DocumentController@download` after verifying policy permissions and logging an entry in `audit_logs`.
