# OrbiTouch ATS — Recruiter, Team Leader & Client Portal User Guide

## 1. Getting Started & Navigation Shortcuts

- **Sign In:** Access `https://crm.orbitouch-hr.com/login` with your corporate OrbiTouch email and password.
- **Global Command Palette (`Ctrl+K`):** Press `Ctrl+K` from any screen to search across candidates, phone numbers, email addresses, Candidate IDs (`CND-`), skills, companies, Job IDs (`JOB-`), and clients.
- **360° Candidate Quick Preview:** Click **Preview** or **360° Preview** on any candidate row or Kanban card to slide open the right-side preview drawer without leaving your current page.

---

## 2. Recruiter Daily Workflow (`My Work`)

1. **Review Assigned Requirements & Daily Targets:**
   - Open **Workspace → My Work** (`/my-work`) to view your daily sourcing, calling, and profile submission targets along with your primary and secondary job assignments.
2. **Source Candidates & Prevent Duplicates:**
   - Use **Quick Candidate Entry** in `My Work` or **Recruitment → Candidates → + Add Candidate Profile**.
   - The system automatically normalizes phone numbers and emails and computes a SHA-256 checksum on uploaded resumes to alert you immediately if the candidate already exists in the master database.
3. **Click-to-Call & Chronological Call Logging:**
   - Open a candidate's 360° profile (`/candidates/{id}`), click their phone number (`tel:` click-to-call), and record the call outcome (`Interested`, `Screening Completed`, `Call Back Requested`, etc.), salary validation, notice period, and next follow-up date.
   - Previous call logs are never overwritten; every conversation is preserved chronologically.
4. **Job Association & Duplicate Effort Prevention:**
   - Associate a candidate with an active Job Requirement. If another recruiter is already working on the same candidate for that vacancy, the system immediately alerts you to prevent duplicate effort.
5. **Submit Profiles to Client:**
   - Submit individual profiles from the candidate profile or use **Recruitment → Profile Submissions → + Bulk Submit Screened Profiles**.
   - Each submission captures an immutable snapshot of the candidate's CTC, experience, notice period, and resume version at the moment of submission.
6. **Interactive Kanban Pipeline:**
   - Navigate to **Recruitment → Recruitment Pipeline** (`/pipeline`) to drag-and-drop candidate cards across the 13 lifecycle stages or switch to the Table View.
7. **Unlimited Multi-Round Interview Scheduling:**
   - Navigate to **Recruitment → Interviews** (`/interviews`) to schedule Round 1..N, track reschedules (`Attempt #1, #2...`), detect time conflicts, and record round scores and results.
8. **Offers, Joinings & Post-Joining Check-ins:**
   - Record offers and salary revisions under **Recruitment → Offers** (`/offers`).
   - Confirm candidate joining under **Recruitment → Joining and Placements** (`/joinings`). Confirming a joining automatically updates the job's remaining vacancy count and schedules 30/60/90-day retention check-ins.

---

## 3. External Client HR Portal Guide

External Client HR users sign in to an isolated portal (`/portal/dashboard`) where they can:
1. **Submit New Hiring Requirements** and upload official Job Description documents.
2. **Review Submitted Profiles** and download authorised resumes.
3. **Compare Candidates Side-by-Side** (`/portal/compare`).
4. **Shortlist or Reject Candidates** with feedback and preferred interview availability.
5. **Record Interview Feedback** and confirm final selections and joinings.
