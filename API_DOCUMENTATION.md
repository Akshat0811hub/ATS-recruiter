# OrbiTouch ATS — Application & JSON Endpoints Documentation

## 1. JSON & Asynchronous Interactive Endpoints

All endpoints require an authenticated session (`auth` middleware) and enforce role-based access control (`CheckRoleOrPermission`) and client organisation isolation (`EnsureClientIsolation`).

### 1.1 Global Command Palette Search (`Ctrl+K`)
- **Endpoint:** `GET /global-search?q={query}`
- **Access:** Authenticated Staff & Client Portal Users (Client users receive strictly isolated results for their own `client_id`).
- **Response (`200 OK`):**
```json
{
  "results": [
    {
      "type": "Candidate",
      "code": "CND-2026-0001",
      "title": "Aarav Sharma",
      "subtitle": "Principal Software Engineer • +91 9876543210",
      "url": "/candidates/1"
    }
  ]
}
```

### 1.2 Candidate 360-Degree Side-Panel Preview
- **Endpoint:** `GET /candidates/{candidate}/preview`
- **Access:** Internal Staff (`super_admin`, `manager`, `team_leader`, `recruiter`).
- **Response (`200 OK`):**
```json
{
  "id": 1,
  "candidate_code": "CND-2026-0001",
  "full_name": "Aarav Sharma",
  "mobile_number": "+91 9876543210",
  "email": "aarav.sharma@example.com",
  "total_experience": "8.5",
  "current_ctc": "2900000.00",
  "expected_ctc": "3600000.00",
  "currency": "INR",
  "notice_period_days": 15,
  "skills": ["Laravel", "System Design", "MySQL", "Kubernetes", "Go"],
  "has_resume": true,
  "resume_download_url": "/documents/1/download"
}
```

### 1.3 Live Candidate Duplicate Detection
- **Endpoint:** `GET /candidates/check-duplicates?mobile_number={phone}&email={email}&alternate_number={alt}&resume_checksum={sha256}`
- **Access:** Internal Staff.
- **Response (`200 OK`):**
```json
{
  "has_duplicates": true,
  "count": 1,
  "duplicates": [
    {
      "id": 1,
      "candidate_code": "CND-2026-0001",
      "full_name": "Aarav Sharma",
      "reasons": ["Mobile Number Match"]
    }
  ]
}
```

### 1.4 Concurrency-Safe Pipeline Stage Transition
- **Endpoint:** `POST /applications/{application}/transition`
- **Headers:** `Accept: application/json`, `X-CSRF-TOKEN: {token}`
- **Payload:**
```json
{
  "target_stage": "interview_scheduled",
  "reason": "Round 1 scheduled with technical panel",
  "lock_version": 1
}
```
- **Responses:**
  - `200 OK`: Stage transitioned transactionally, `lock_version` incremented, `status_history` recorded, follow-up task generated.
  - `409 Conflict`: Optimistic lock mismatch (another user modified the record concurrently) or failed-interview-round progression block.

---

## 2. Future Extensibility Integration Interfaces

In accordance with Section 47, clean configuration flags in `.env` and service boundaries in `app/Services/` are prepared for optional future activation:
- **Gmail API / Google Calendar:** `ATS_GOOGLE_CALENDAR_ENABLED`, `ATS_GMAIL_SYNC_ENABLED`
- **WhatsApp Business API:** `ATS_WHATSAPP_BUSINESS_ENABLED` (via `communication_records.channel = 'whatsapp_optional'`)
- **Cloud Telephony & Lawful Call Recording:** `ATS_CLOUD_TELEPHONY_ENABLED` (via `call_logs`)
- **S3-Compatible Object Storage Vault:** `FILESYSTEM_DISK=s3` in `StorageManagementService`
