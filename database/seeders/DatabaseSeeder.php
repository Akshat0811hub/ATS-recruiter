<?php

namespace Database\Seeders;

use App\Models\CallLog;
use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\CandidateSkill;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientSubmission;
use App\Models\CustomField;
use App\Models\EmailTemplate;
use App\Models\InterviewRecord;
use App\Models\InterviewRoundDefinition;
use App\Models\JobAssignment;
use App\Models\JobRequirement;
use App\Models\JobScreeningQuestion;
use App\Models\JoiningRecord;
use App\Models\Offer;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Placement;
use App\Models\Role;
use App\Models\ScreeningRecord;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $org = $this->seedCoreFoundation();

        if (! app()->environment('production')) {
            $this->seedOperationalShowcaseData($org);
        }
    }

    public function seedCoreFoundation(): Organisation
    {
        $org = Organisation::firstOrCreate(
            ['legal_name' => 'Orbitouch Outsourcing Private Limited'],
            [
                'name' => 'OrbiTouch HR & Recruitment Consultants',
                'website' => 'https://www.orbitouch-hr.com/',
                'crm_url' => 'https://crm.orbitouch-hr.com/',
                'default_timezone' => 'Asia/Kolkata',
                'default_currency' => 'INR',
                'working_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                'work_start_time' => '10:00',
                'work_end_time' => '18:30',
                'default_replacement_days' => 90,
                'storage_quota_bytes' => 19510000000,
            ]
        );

        // Roles
        $rolesData = [
            'super_admin' => ['name' => 'Super Admin', 'description' => 'Complete authorised system access across all operations, settings, backups, and security.'],
            'manager' => ['name' => 'Manager', 'description' => 'Monitors assigned teams, manages client requirements, assigns recruiters, and reviews performance.'],
            'team_leader' => ['name' => 'Team Leader', 'description' => 'Manages team requirements, daily sourcing targets, candidate submissions, and follow-ups.'],
            'recruiter' => ['name' => 'Recruiter', 'description' => 'Sources candidates, logs calls, performs screenings, submits profiles, and coordinates interviews & offers.'],
            'client' => ['name' => 'Client HR', 'description' => 'External client organisation HR representative with isolated portal access.'],
        ];

        $roles = [];
        foreach ($rolesData as $slug => $info) {
            $roles[$slug] = Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => $info['name'], 'description' => $info['description'], 'is_system' => true]
            );
        }

        // Permissions
        $permissionsConfig = [
            'dashboard.executive' => ['Executive Dashboard', 'dashboard', ['super_admin', 'manager']],
            'dashboard.team' => ['Team & Manager Dashboard', 'dashboard', ['super_admin', 'manager', 'team_leader']],
            'dashboard.recruiter' => ['Recruiter Workspace', 'dashboard', ['super_admin', 'manager', 'team_leader', 'recruiter']],
            'dashboard.client_portal' => ['Client Portal Access', 'dashboard', ['client']],
            'users.manage' => ['Manage Employees & Invitations', 'administration', ['super_admin']],
            'roles.manage' => ['Manage Roles & Permissions', 'administration', ['super_admin']],
            'teams.manage' => ['Manage Teams & Workload', 'management', ['super_admin', 'manager', 'team_leader']],
            'clients.view' => ['View Clients', 'clients', ['super_admin', 'manager', 'team_leader', 'recruiter']],
            'clients.manage' => ['Create & Edit Clients', 'clients', ['super_admin', 'manager']],
            'jobs.view' => ['View Job Requirements', 'jobs', ['super_admin', 'manager', 'team_leader', 'recruiter', 'client']],
            'jobs.create' => ['Create Job Requirements', 'jobs', ['super_admin', 'manager', 'team_leader', 'client']],
            'jobs.update' => ['Update Job Requirements', 'jobs', ['super_admin', 'manager', 'team_leader', 'client']],
            'jobs.assign' => ['Assign Recruiters to Jobs', 'jobs', ['super_admin', 'manager', 'team_leader']],
            'candidates.view' => ['View Candidate Master Database', 'candidates', ['super_admin', 'manager', 'team_leader', 'recruiter']],
            'candidates.create' => ['Create Candidate Profiles', 'candidates', ['super_admin', 'manager', 'team_leader', 'recruiter']],
            'candidates.update' => ['Update Candidate Profiles', 'candidates', ['super_admin', 'manager', 'team_leader', 'recruiter']],
            'candidates.merge' => ['Merge Duplicate Candidates', 'candidates', ['super_admin', 'manager']],
            'candidates.import' => ['Bulk Import Candidates', 'candidates', ['super_admin', 'manager', 'team_leader']],
            'candidates.export' => ['Export Candidate Data', 'candidates', ['super_admin', 'manager']],
            'calls.record' => ['Record Call Discussions', 'operations', ['super_admin', 'manager', 'team_leader', 'recruiter']],
            'screening.perform' => ['Perform Candidate Screening', 'operations', ['super_admin', 'manager', 'team_leader', 'recruiter']],
            'submissions.create' => ['Submit Profiles to Clients', 'operations', ['super_admin', 'manager', 'team_leader', 'recruiter']],
            'submissions.decide' => ['Record Client Shortlist/Rejection', 'operations', ['super_admin', 'manager', 'team_leader', 'recruiter', 'client']],
            'interviews.schedule' => ['Schedule & Reschedule Interviews', 'interviews', ['super_admin', 'manager', 'team_leader', 'recruiter', 'client']],
            'interviews.feedback' => ['Record Interview Results & Scores', 'interviews', ['super_admin', 'manager', 'team_leader', 'recruiter', 'client']],
            'interviews.override' => ['Override Failed Interview Rounds', 'interviews', ['super_admin', 'manager']],
            'offers.manage' => ['Manage Offers & Salary Negotiation', 'offers', ['super_admin', 'manager', 'team_leader', 'recruiter']],
            'joinings.manage' => ['Manage Candidate Joining & Placements', 'joinings', ['super_admin', 'manager', 'team_leader', 'recruiter', 'client']],
            'replacements.reopen' => ['Authorise Replacement Vacancy Reopening', 'joinings', ['super_admin', 'manager']],
            'tasks.manage' => ['Manage Tasks & Follow-ups', 'productivity', ['super_admin', 'manager', 'team_leader', 'recruiter']],
            'reports.view' => ['View Recruitment Reports', 'reports', ['super_admin', 'manager', 'team_leader', 'recruiter']],
            'reports.export' => ['Export Business Reports', 'reports', ['super_admin', 'manager']],
            'audit_logs.view' => ['View Immutable Audit Logs', 'administration', ['super_admin']],
            'settings.manage' => ['Configure Business & System Settings', 'administration', ['super_admin']],
            'storage.manage' => ['Manage Document Storage & Quotas', 'administration', ['super_admin']],
            'backups.manage' => ['Manage Backups & Data Recovery', 'administration', ['super_admin']],
            'system.health' => ['View System Health Diagnostics', 'administration', ['super_admin']],
        ];

        $rolePermissionsMap = [
            'super_admin' => [],
            'manager' => [],
            'team_leader' => [],
            'recruiter' => [],
            'client' => [],
        ];

        foreach ($permissionsConfig as $slug => [$name, $module, $assignedRoles]) {
            $perm = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'module' => $module]
            );
            foreach ($assignedRoles as $rSlug) {
                $rolePermissionsMap[$rSlug][] = $perm->id;
            }
        }

        foreach ($rolePermissionsMap as $rSlug => $permIds) {
            $roles[$rSlug]->permissions()->sync($permIds);
        }

        // System Settings
        $defaultSettings = [
            ['company', 'company_name', 'OrbiTouch HR & Recruitment Consultants', 'string', 'Company Brand Name'],
            ['company', 'legal_company_name', 'Orbitouch Outsourcing Private Limited', 'string', 'Legal Entity Name'],
            ['company', 'default_timezone', 'Asia/Kolkata', 'string', 'Default Timezone'],
            ['company', 'default_currency', 'INR', 'string', 'Default Currency'],
            ['schedule', 'working_days', json_encode(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']), 'json', 'Working Days'],
            ['schedule', 'work_hours_start', '10:00', 'string', 'Work Day Start (IST)'],
            ['schedule', 'work_hours_end', '18:30', 'string', 'Work Day End (IST)'],
            ['recruitment', 'default_replacement_days', '90', 'integer', 'Standard Replacement Period (Days)'],
            ['recruitment', 'candidate_sources', json_encode(['Direct Sourcing', 'Naukri', 'LinkedIn Recruiter', 'Employee Referral', 'OrbiTouch Career Portal', 'Headhunting', 'Database Reactivation']), 'json', 'Candidate Sources'],
            ['recruitment', 'rejection_reasons', json_encode(['Technical Skills Gap', 'Salary Expectation Mismatch', 'Notice Period Too Long', 'Communication Assessment', 'Position Filled Internally', 'Location Constraint', 'Counter Offer Accepted']), 'json', 'Standard Rejection Reasons'],
            ['storage', 'storage_quota_bytes', '19510000000', 'integer', 'Available Hostinger Storage Quota (Bytes)'],
            ['storage', 'storage_warn_70', '70', 'integer', 'Warning Alert Threshold (%)'],
            ['storage', 'storage_warn_85', '85', 'integer', 'High Utilisation Threshold (%)'],
            ['storage', 'storage_warn_95', '95', 'integer', 'Critical Utilisation Threshold (%)'],
            ['privacy', 'dpdp_retention_months', '36', 'integer', 'Candidate Data Retention Policy (Months)'],
            ['privacy', 'privacy_notice_text', 'OrbiTouch HR & Recruitment Consultants processes candidate personal data strictly for lawful recruitment and placement evaluation with explicit candidate consent in accordance with the Digital Personal Data Protection (DPDP) Act.', 'string', 'Candidate Privacy Notice'],
        ];

        foreach ($defaultSettings as [$group, $key, $value, $type, $label]) {
            SystemSetting::firstOrCreate(
                ['key' => $key],
                ['group' => $group, 'value' => $value, 'type' => $type, 'label' => $label]
            );
        }

        // Email Templates (11 required templates)
        $templates = [
            [
                'slug' => 'client_requirement_acknowledgement',
                'name' => 'Client Requirement Acknowledgement',
                'subject' => 'Requirement Received: {{job_title}} ({{job_code}}) — OrbiTouch HR',
                'body_html' => '<p>Dear {{client_name}},</p><p>Thank you for sharing the hiring requirement for <strong>{{job_title}}</strong> ({{openings_count}} openings). Our recruitment team has initiated sourcing and will submit screened profiles shortly.</p><p>Warm regards,<br>OrbiTouch HR & Recruitment Consultants</p>',
                'available_variables' => ['client_name', 'job_title', 'job_code', 'openings_count'],
            ],
            [
                'slug' => 'recruiter_assignment',
                'name' => 'Recruiter Requirement Assignment',
                'subject' => 'New Requirement Assigned: {{job_title}} ({{job_code}})',
                'body_html' => '<p>Hello {{recruiter_name}},</p><p>You have been assigned to work on <strong>{{job_title}}</strong> for <strong>{{client_name}}</strong>. Daily submission target: {{submission_target}} profiles.</p>',
                'available_variables' => ['recruiter_name', 'job_title', 'job_code', 'client_name', 'submission_target'],
            ],
            [
                'slug' => 'candidate_profile_submission',
                'name' => 'Candidate Profile Submission to Client',
                'subject' => 'Profile Submission: {{candidate_name}} for {{job_title}} ({{job_code}})',
                'body_html' => '<p>Dear {{client_name}},</p><p>Please find submitted the screened profile of <strong>{{candidate_name}}</strong> for <strong>{{job_title}}</strong>. You may review the resume and shortlist directly in the OrbiTouch Client Portal.</p>',
                'available_variables' => ['client_name', 'candidate_name', 'job_title', 'job_code'],
            ],
            [
                'slug' => 'interview_invitation',
                'name' => 'Candidate Interview Invitation',
                'subject' => 'Interview Invitation: {{round_name}} for {{job_title}} at {{client_name}}',
                'body_html' => '<p>Dear {{candidate_name}},</p><p>Your <strong>{{round_name}}</strong> for <strong>{{job_title}}</strong> with <strong>{{client_name}}</strong> is scheduled on {{interview_date}} at {{interview_time}} ({{mode}}).</p>',
                'available_variables' => ['candidate_name', 'round_name', 'job_title', 'client_name', 'interview_date', 'interview_time', 'mode'],
            ],
            [
                'slug' => 'interview_reminder',
                'name' => 'Interview Reminder',
                'subject' => 'Reminder: Upcoming Interview for {{job_title}} ({{round_name}})',
                'body_html' => '<p>Dear {{candidate_name}},</p><p>This is a reminder for your upcoming interview ({{round_name}}) scheduled on {{interview_date}} at {{interview_time}}.</p>',
                'available_variables' => ['candidate_name', 'job_title', 'round_name', 'interview_date', 'interview_time'],
            ],
            [
                'slug' => 'interview_rescheduling',
                'name' => 'Interview Rescheduled Notification',
                'subject' => 'Rescheduled: {{round_name}} for {{job_title}}',
                'body_html' => '<p>Dear {{candidate_name}},</p><p>Your interview for <strong>{{job_title}}</strong> has been rescheduled to {{interview_date}} at {{interview_time}}.</p>',
                'available_variables' => ['candidate_name', 'round_name', 'job_title', 'interview_date', 'interview_time'],
            ],
            [
                'slug' => 'interview_cancellation',
                'name' => 'Interview Cancellation Notice',
                'subject' => 'Cancelled: Interview for {{job_title}} ({{round_name}})',
                'body_html' => '<p>Dear {{candidate_name}},</p><p>Please note that the scheduled interview ({{round_name}}) for {{job_title}} has been cancelled. Your recruiter will connect with you regarding next steps.</p>',
                'available_variables' => ['candidate_name', 'job_title', 'round_name'],
            ],
            [
                'slug' => 'client_feedback_reminder',
                'name' => 'Client Feedback Follow-up Reminder',
                'subject' => 'Pending Review: Submitted Profiles for {{job_title}} ({{job_code}})',
                'body_html' => '<p>Dear {{client_name}},</p><p>We await your feedback on the profiles submitted for <strong>{{job_title}}</strong>. Prompt shortlisting helps us secure high-demand candidates early.</p>',
                'available_variables' => ['client_name', 'job_title', 'job_code'],
            ],
            [
                'slug' => 'offer_follow_up',
                'name' => 'Offer Acceptance Follow-up',
                'subject' => 'Offer Letter Follow-up: {{offered_designation}} at {{client_name}}',
                'body_html' => '<p>Dear {{candidate_name}},</p><p>Congratulations on your offer for <strong>{{offered_designation}}</strong>. Kindly confirm your formal acceptance and expected joining date ({{expected_joining_date}}).</p>',
                'available_variables' => ['candidate_name', 'offered_designation', 'client_name', 'expected_joining_date'],
            ],
            [
                'slug' => 'joining_reminder',
                'name' => 'Upcoming Joining Date Reminder',
                'subject' => 'Onboarding & Joining Reminder: {{expected_joining_date}} at {{client_name}}',
                'body_html' => '<p>Dear {{candidate_name}},</p><p>We look forward to your joining at <strong>{{client_name}}</strong> on {{expected_joining_date}}. Please keep your onboarding documents ready.</p>',
                'available_variables' => ['candidate_name', 'client_name', 'expected_joining_date'],
            ],
            [
                'slug' => 'joining_confirmation',
                'name' => 'Candidate Joining Confirmation',
                'subject' => 'Joining Confirmed: {{candidate_name}} — {{job_title}}',
                'body_html' => '<p>Dear {{client_name}},</p><p>We are pleased to confirm that <strong>{{candidate_name}}</strong> has joined as <strong>{{job_title}}</strong> on {{actual_joining_date}}.</p>',
                'available_variables' => ['client_name', 'candidate_name', 'job_title', 'actual_joining_date'],
            ],
        ];

        foreach ($templates as $tpl) {
            EmailTemplate::firstOrCreate(
                ['slug' => $tpl['slug']],
                $tpl
            );
        }

        // Custom Fields
        CustomField::firstOrCreate(
            ['entity_type' => 'candidate', 'field_key' => 'pan_verified'],
            ['field_label' => 'ID / Employment Eligibility Verified', 'field_type' => 'boolean', 'is_required' => false, 'sort_order' => 1]
        );
        CustomField::firstOrCreate(
            ['entity_type' => 'job_requirement', 'field_key' => 'shift_timing'],
            ['field_label' => 'Shift Timing', 'field_type' => 'select', 'options' => ['Day Shift (IST)', 'UK Shift', 'US Overlapping Shift', 'Flexible'], 'is_required' => false, 'sort_order' => 1]
        );

        // Super Admin user (always ensured)
        User::firstOrCreate(
            ['email' => 'admin@orbitouch-hr.com'],
            [
                'employee_code' => 'ORB-ADM-001',
                'organisation_id' => $org->id,
                'role_id' => $roles['super_admin']->id,
                'name' => 'Vikramaditya Singh (Super Admin)',
                'phone' => '+91 9820011001',
                'designation' => 'Managing Director & Super Admin',
                'email_verified_at' => now(),
                'password' => Hash::make('OrbiTouch@2026!'),
                'is_active' => true,
            ]
        );

        return $org;
    }

    private function seedOperationalShowcaseData(Organisation $org): void
    {
        $roles = Role::pluck('id', 'slug');

        $manager = User::firstOrCreate(
            ['email' => 'manager@orbitouch-hr.com'],
            [
                'employee_code' => 'ORB-MGR-001',
                'organisation_id' => $org->id,
                'role_id' => $roles['manager'],
                'name' => 'Ananya Deshmukh',
                'phone' => '+91 9820011002',
                'designation' => 'VP - Talent Delivery',
                'email_verified_at' => now(),
                'password' => Hash::make('OrbiTouch@2026!'),
                'is_active' => true,
            ]
        );

        $teamLeader = User::firstOrCreate(
            ['email' => 'tl@orbitouch-hr.com'],
            [
                'employee_code' => 'ORB-TL-001',
                'organisation_id' => $org->id,
                'role_id' => $roles['team_leader'],
                'name' => 'Rohan Kulkarni',
                'phone' => '+91 9820011003',
                'designation' => 'Team Leader - Enterprise & Technology',
                'email_verified_at' => now(),
                'password' => Hash::make('OrbiTouch@2026!'),
                'is_active' => true,
            ]
        );

        $team = Team::firstOrCreate(
            ['code' => 'TM-ENT-01'],
            [
                'name' => 'Enterprise Technology & BFSI Practice',
                'specialization' => 'IT, Digital Engineering & FinTech',
                'manager_id' => $manager->id,
                'team_leader_id' => $teamLeader->id,
                'daily_sourcing_target' => 60,
                'daily_call_target' => 200,
                'daily_submission_target' => 25,
                'is_active' => true,
            ]
        );

        $teamLeader->update(['team_id' => $team->id]);

        $recruiter1 = User::firstOrCreate(
            ['email' => 'recruiter@orbitouch-hr.com'],
            [
                'employee_code' => 'ORB-REC-001',
                'organisation_id' => $org->id,
                'role_id' => $roles['recruiter'],
                'team_id' => $team->id,
                'name' => 'Priya Nair',
                'phone' => '+91 9820011004',
                'designation' => 'Senior Talent Acquisition Specialist',
                'email_verified_at' => now(),
                'password' => Hash::make('OrbiTouch@2026!'),
                'is_active' => true,
                'daily_sourcing_target' => 12,
                'daily_call_target' => 40,
                'daily_submission_target' => 6,
            ]
        );

        $recruiter2 = User::firstOrCreate(
            ['email' => 'recruiter2@orbitouch-hr.com'],
            [
                'employee_code' => 'ORB-REC-002',
                'organisation_id' => $org->id,
                'role_id' => $roles['recruiter'],
                'team_id' => $team->id,
                'name' => 'Siddharth Verma',
                'phone' => '+91 9820011005',
                'designation' => 'Talent Acquisition Executive',
                'email_verified_at' => now(),
                'password' => Hash::make('OrbiTouch@2026!'),
                'is_active' => true,
                'daily_sourcing_target' => 10,
                'daily_call_target' => 35,
                'daily_submission_target' => 5,
            ]
        );

        $team->members()->syncWithoutDetaching([
            $manager->id => ['team_role' => 'manager', 'is_active' => true],
            $teamLeader->id => ['team_role' => 'team_leader', 'is_active' => true],
            $recruiter1->id => ['team_role' => 'recruiter', 'is_active' => true],
            $recruiter2->id => ['team_role' => 'recruiter', 'is_active' => true],
        ]);

        // External Client 1
        $client1 = Client::firstOrCreate(
            ['company_name' => 'Apex FinTech Global Solutions Pvt Ltd'],
            [
                'client_code' => 'CLT-2026-0001',
                'industry' => 'FinTech & Enterprise Software',
                'website' => 'https://apexfintech.example.com',
                'address' => 'Tower B, Bandra Kurla Complex (BKC), Mumbai',
                'city' => 'Mumbai',
                'country' => 'India',
                'locations' => ['Mumbai', 'Bengaluru', 'Pune'],
                'primary_hr_name' => 'Meera Krishnan',
                'primary_hr_designation' => 'Head of Talent Acquisition',
                'primary_hr_phone' => '+91 9811122001',
                'primary_hr_email' => 'client@apexfintech.example.com',
                'account_manager_id' => $manager->id,
                'assigned_team_id' => $team->id,
                'status' => 'active',
                'placement_fee_percentage' => 8.33,
                'currency' => 'INR',
                'replacement_period_days' => 90,
                'payment_terms_days' => 30,
                'recruitment_terms' => '8.33% of Annual Fixed CTC; 90-day free one-time replacement guarantee.',
                'internal_notes' => 'Tier-1 strategic account. Requires strict pre-screening on distributed systems.',
            ]
        );

        $clientUser1 = User::firstOrCreate(
            ['email' => 'client@apexfintech.example.com'],
            [
                'employee_code' => 'CLT-USR-001',
                'organisation_id' => $org->id,
                'role_id' => $roles['client'],
                'client_id' => $client1->id,
                'name' => 'Meera Krishnan (Apex FinTech HR)',
                'phone' => '+91 9811122001',
                'designation' => 'Head of Talent Acquisition',
                'email_verified_at' => now(),
                'password' => Hash::make('OrbiTouch@2026!'),
                'is_active' => true,
            ]
        );

        ClientContact::firstOrCreate(
            ['client_id' => $client1->id, 'email' => 'client@apexfintech.example.com'],
            [
                'user_id' => $clientUser1->id,
                'name' => 'Meera Krishnan',
                'designation' => 'Head of Talent Acquisition',
                'phone' => '+91 9811122001',
                'department' => 'Human Resources',
                'is_primary' => true,
                'can_access_portal' => true,
            ]
        );

        // External Client 2 (for isolation verification)
        $client2 = Client::firstOrCreate(
            ['company_name' => 'NovaMed Healthcare & Diagnostics Ltd'],
            [
                'client_code' => 'CLT-2026-0002',
                'industry' => 'Healthcare & Life Sciences',
                'website' => 'https://novamed.example.com',
                'address' => 'Cyber City, DLF Phase 3, Gurugram',
                'city' => 'Gurugram',
                'country' => 'India',
                'locations' => ['Gurugram', 'Hyderabad'],
                'primary_hr_name' => 'Rajeshwari Rao',
                'primary_hr_designation' => 'GM - Human Resources',
                'primary_hr_phone' => '+91 9811122002',
                'primary_hr_email' => 'hr@novamed.example.com',
                'account_manager_id' => $manager->id,
                'assigned_team_id' => $team->id,
                'status' => 'active',
                'placement_fee_percentage' => 10.00,
                'currency' => 'INR',
                'replacement_period_days' => 90,
                'payment_terms_days' => 45,
            ]
        );

        User::firstOrCreate(
            ['email' => 'hr@novamed.example.com'],
            [
                'employee_code' => 'CLT-USR-002',
                'organisation_id' => $org->id,
                'role_id' => $roles['client'],
                'client_id' => $client2->id,
                'name' => 'Rajeshwari Rao (NovaMed HR)',
                'phone' => '+91 9811122002',
                'designation' => 'GM - Human Resources',
                'email_verified_at' => now(),
                'password' => Hash::make('OrbiTouch@2026!'),
                'is_active' => true,
            ]
        );

        // Job Requirement 1 (5 interview rounds, 3 openings)
        $job1 = JobRequirement::firstOrCreate(
            ['job_code' => 'JOB-2026-0001'],
            [
                'client_id' => $client1->id,
                'title' => 'Lead Cloud & Distributed Systems Architect',
                'openings_count' => 3,
                'joined_count' => 1,
                'remaining_count' => 2,
                'department' => 'Core Platform Engineering',
                'industry' => 'FinTech & Enterprise Software',
                'description' => 'Architect high-throughput payment microservices, supervise cloud infrastructure resilience, and mentor engineering squads.',
                'required_education' => 'B.Tech / M.Tech in Computer Science or equivalent',
                'required_skills' => ['PHP', 'Laravel', 'Go', 'MySQL', 'Kubernetes', 'System Design'],
                'preferred_skills' => ['AWS', 'Kafka', 'Payment Gateways'],
                'min_experience' => 6.0,
                'max_experience' => 12.0,
                'min_salary' => 2800000,
                'max_salary' => 4200000,
                'currency' => 'INR',
                'location' => 'Mumbai (BKC)',
                'country' => 'India',
                'work_mode' => 'hybrid',
                'employment_type' => 'full_time',
                'notice_period_preference' => 'Immediate to 30 Days',
                'max_notice_days' => 45,
                'priority' => 'critical',
                'expected_closure_date' => now()->addDays(21)->toDateString(),
                'interview_rounds_count' => 5,
                'client_instructions' => 'Focus on hands-on database indexing and high-availability system design.',
                'internal_instructions' => 'Prioritise candidates currently serving notice or with <= 30 days official notice.',
                'status' => 'partially_filled',
                'assigned_team_id' => $team->id,
                'created_by' => $manager->id,
                'approved_by' => $manager->id,
                'approved_at' => now()->subDays(10),
            ]
        );

        $rounds = [
            [1, 'Round 1: HR Screening', 'telephonic', 30],
            [2, 'Round 2: Technical Interview', 'video', 60],
            [3, 'Round 3: Technical Assessment & Architecture', 'technical_assessment', 90],
            [4, 'Round 4: VP Engineering / Management Interview', 'video', 45],
            [5, 'Round 5: Final HR & Compensation Discussion', 'face_to_face', 30],
        ];
        foreach ($rounds as [$num, $rName, $mode, $dur]) {
            InterviewRoundDefinition::firstOrCreate(
                ['job_requirement_id' => $job1->id, 'round_number' => $num],
                ['round_name' => $rName, 'interview_mode' => $mode, 'default_duration_minutes' => $dur, 'is_mandatory' => true]
            );
        }

        JobScreeningQuestion::firstOrCreate(
            ['job_requirement_id' => $job1->id, 'sort_order' => 1],
            ['question' => 'Has the candidate designed multi-tenant relational databases handling > 10M rows?', 'question_type' => 'boolean', 'expected_answer' => 'Yes', 'is_mandatory' => true]
        );

        JobAssignment::firstOrCreate(
            ['job_requirement_id' => $job1->id, 'recruiter_id' => $recruiter1->id],
            ['assigned_by' => $teamLeader->id, 'is_primary' => true, 'daily_sourcing_target' => 6, 'submission_target' => 8, 'priority' => 'critical', 'status' => 'active']
        );
        JobAssignment::firstOrCreate(
            ['job_requirement_id' => $job1->id, 'recruiter_id' => $recruiter2->id],
            ['assigned_by' => $teamLeader->id, 'is_primary' => false, 'daily_sourcing_target' => 4, 'submission_target' => 5, 'priority' => 'high', 'status' => 'active']
        );

        // Candidate 1 (Joined through all 5 rounds)
        $cand1 = Candidate::firstOrCreate(
            ['candidate_code' => 'CND-2026-0001'],
            [
                'full_name' => 'Aarav Sharma',
                'mobile_number' => '+91 9876543210',
                'email' => 'aarav.sharma@example.com',
                'current_location' => 'Mumbai',
                'preferred_location' => 'Mumbai',
                'country' => 'India',
                'current_employer' => 'PaySphere Technologies',
                'current_designation' => 'Principal Software Engineer',
                'total_experience' => 8.5,
                'relevant_experience' => 7.0,
                'qualifications' => 'B.Tech CS — IIT Bombay',
                'industry' => 'FinTech',
                'current_ctc' => 2900000,
                'expected_ctc' => 3600000,
                'currency' => 'INR',
                'notice_period_days' => 15,
                'is_serving_notice' => true,
                'source' => 'Direct Sourcing',
                'assigned_recruiter_id' => $recruiter1->id,
                'created_by' => $recruiter1->id,
                'tags' => ['Top Performer', 'Immediate Joiner', 'FinTech'],
                'current_stage' => 'joined',
                'last_contacted_at' => now()->subDays(1),
            ]
        );

        foreach (['Laravel', 'System Design', 'MySQL', 'Kubernetes', 'Go'] as $sk) {
            CandidateSkill::firstOrCreate(['candidate_id' => $cand1->id, 'skill_name' => $sk], ['experience_years' => 6.0, 'proficiency' => 'expert']);
        }

        $app1 = CandidateApplication::firstOrCreate(
            ['candidate_id' => $cand1->id, 'job_requirement_id' => $job1->id],
            [
                'application_code' => 'APP-2026-0001',
                'client_id' => $client1->id,
                'recruiter_id' => $recruiter1->id,
                'pipeline_stage' => 'joined',
                'applied_ctc' => 2900000,
                'expected_ctc' => 3600000,
                'notice_period_days' => 15,
                'current_interview_round' => 5,
            ]
        );

        CallLog::firstOrCreate(
            ['candidate_id' => $cand1->id, 'job_requirement_id' => $job1->id],
            [
                'candidate_application_id' => $app1->id,
                'recruiter_id' => $recruiter1->id,
                'called_at' => now()->subDays(9),
                'duration_seconds' => 780,
                'outcome' => 'screening_completed',
                'candidate_interest' => 'High',
                'discussion_summary' => 'Detailed technical screening completed. Candidate leads a 12-member payment settlement squad and is serving his final 15 days of notice.',
                'current_ctc' => 2900000,
                'expected_ctc' => 3600000,
                'notice_period_days' => 15,
                'interview_availability' => 'Weekdays 11:00 AM - 5:00 PM IST',
                'next_action' => 'Submit profile to Apex FinTech',
            ]
        );

        ScreeningRecord::firstOrCreate(
            ['candidate_application_id' => $app1->id],
            [
                'candidate_id' => $cand1->id,
                'job_requirement_id' => $job1->id,
                'recruiter_id' => $recruiter1->id,
                'communication_rating' => 5,
                'screening_status' => 'passed',
                'assessment_summary' => 'Strong match on distributed systems, MySQL performance optimization, and leadership.',
                'recruiter_recommendation' => 'Strongly recommended for immediate client shortlist.',
            ]
        );

        ClientSubmission::firstOrCreate(
            ['candidate_id' => $cand1->id, 'job_requirement_id' => $job1->id],
            [
                'submission_code' => 'SUB-2026-0001',
                'candidate_application_id' => $app1->id,
                'client_id' => $client1->id,
                'recruiter_id' => $recruiter1->id,
                'snapshot_candidate_name' => $cand1->full_name,
                'snapshot_designation' => $cand1->current_designation,
                'snapshot_employer' => $cand1->current_employer,
                'snapshot_relevant_experience' => 7.0,
                'snapshot_total_experience' => 8.5,
                'snapshot_current_ctc' => 2900000,
                'snapshot_expected_ctc' => 3600000,
                'snapshot_notice_period_days' => 15,
                'snapshot_location' => 'Mumbai',
                'screening_summary' => '8.5 yrs experience, IIT Bombay alumnus, serving last 15 days notice.',
                'candidate_availability' => 'Available daily 11 AM - 5 PM IST',
                'recruiter_recommendation' => 'Top 5% architect profile.',
                'client_decision' => 'shortlisted',
                'decided_by' => $clientUser1->id,
                'decided_at' => now()->subDays(7),
            ]
        );

        foreach ($rounds as [$num, $rName, $mode, $dur]) {
            InterviewRecord::firstOrCreate(
                ['candidate_application_id' => $app1->id, 'round_number' => $num],
                [
                    'job_requirement_id' => $job1->id,
                    'client_id' => $client1->id,
                    'candidate_id' => $cand1->id,
                    'round_name' => $rName,
                    'interviewer_name' => 'Apex Panel R'.$num,
                    'interviewer_email' => "panel{$num}@apexfintech.example.com",
                    'interview_date' => now()->subDays(8 - $num)->toDateString(),
                    'interview_time' => '14:00',
                    'scheduled_at' => now()->subDays(8 - $num)->setTime(14, 0),
                    'duration_minutes' => $dur,
                    'mode' => $mode,
                    'candidate_confirmed' => true,
                    'client_confirmed' => true,
                    'status' => 'passed',
                    'result' => 'passed',
                    'score' => 9.0,
                    'feedback_summary' => "Cleared {$rName} with strong evaluation.",
                    'scheduled_by' => $recruiter1->id,
                ]
            );
        }

        $offer1 = Offer::firstOrCreate(
            ['candidate_application_id' => $app1->id],
            [
                'offer_code' => 'OFR-2026-0001',
                'job_requirement_id' => $job1->id,
                'client_id' => $client1->id,
                'candidate_id' => $cand1->id,
                'recruiter_id' => $recruiter1->id,
                'selection_date' => now()->subDays(3)->toDateString(),
                'offered_designation' => 'Lead Cloud & Distributed Systems Architect',
                'offered_salary' => 3500000,
                'negotiated_salary' => 3600000,
                'currency' => 'INR',
                'offer_release_date' => now()->subDays(3)->toDateString(),
                'expected_joining_date' => now()->subDays(1)->toDateString(),
                'candidate_acceptance_date' => now()->subDays(2)->toDateString(),
                'status' => 'offer_accepted',
                'negotiation_notes' => 'Closed at 36.00 LPA fixed.',
            ]
        );

        $joining1 = JoiningRecord::firstOrCreate(
            ['candidate_application_id' => $app1->id],
            [
                'joining_code' => 'JON-2026-0001',
                'offer_id' => $offer1->id,
                'job_requirement_id' => $job1->id,
                'client_id' => $client1->id,
                'candidate_id' => $cand1->id,
                'recruiter_id' => $recruiter1->id,
                'expected_joining_date' => now()->subDays(1)->toDateString(),
                'actual_joining_date' => now()->subDays(1)->toDateString(),
                'candidate_confirmed' => true,
                'client_confirmed' => true,
                'status' => 'joined',
                'final_salary' => 3600000,
                'placement_value' => 299880,
                'currency' => 'INR',
                'replacement_period_days' => 90,
                'replacement_expiry_date' => now()->addDays(89)->toDateString(),
                'next_checkin_date' => now()->addDays(29)->toDateString(),
                'vacancy_counted' => true,
            ]
        );

        Placement::firstOrCreate(
            ['candidate_application_id' => $app1->id],
            [
                'placement_code' => 'PLC-2026-0001',
                'joining_record_id' => $joining1->id,
                'job_requirement_id' => $job1->id,
                'client_id' => $client1->id,
                'candidate_id' => $cand1->id,
                'recruiter_id' => $recruiter1->id,
                'joined_date' => now()->subDays(1)->toDateString(),
                'final_ctc' => 3600000,
                'billable_fee' => 299880,
                'currency' => 'INR',
                'warranty_end_date' => now()->addDays(89)->toDateString(),
                'status' => 'active_warranty',
            ]
        );

        // Candidate 2 (In Interview Round 2)
        $cand2 = Candidate::firstOrCreate(
            ['candidate_code' => 'CND-2026-0002'],
            [
                'full_name' => 'Divya Menon',
                'mobile_number' => '+91 9876543211',
                'email' => 'divya.menon@example.com',
                'current_location' => 'Bengaluru',
                'preferred_location' => 'Mumbai / Hybrid',
                'country' => 'India',
                'current_employer' => 'CloudScale Systems',
                'current_designation' => 'Senior Backend Architect',
                'total_experience' => 7.5,
                'relevant_experience' => 6.5,
                'qualifications' => 'M.Tech Software Systems — BITS Pilani',
                'industry' => 'Enterprise SaaS',
                'current_ctc' => 2600000,
                'expected_ctc' => 3300000,
                'currency' => 'INR',
                'notice_period_days' => 30,
                'source' => 'LinkedIn Recruiter',
                'assigned_recruiter_id' => $recruiter1->id,
                'created_by' => $recruiter1->id,
                'tags' => ['Cloud Architect', 'BITS Pilani'],
                'current_stage' => 'interview_scheduled',
                'last_contacted_at' => now(),
            ]
        );

        $app2 = CandidateApplication::firstOrCreate(
            ['candidate_id' => $cand2->id, 'job_requirement_id' => $job1->id],
            [
                'application_code' => 'APP-2026-0002',
                'client_id' => $client1->id,
                'recruiter_id' => $recruiter1->id,
                'pipeline_stage' => 'interview_scheduled',
                'applied_ctc' => 2600000,
                'expected_ctc' => 3300000,
                'notice_period_days' => 30,
                'current_interview_round' => 2,
            ]
        );

        ClientSubmission::firstOrCreate(
            ['candidate_id' => $cand2->id, 'job_requirement_id' => $job1->id],
            [
                'submission_code' => 'SUB-2026-0002',
                'candidate_application_id' => $app2->id,
                'client_id' => $client1->id,
                'recruiter_id' => $recruiter1->id,
                'snapshot_candidate_name' => $cand2->full_name,
                'snapshot_designation' => $cand2->current_designation,
                'snapshot_employer' => $cand2->current_employer,
                'snapshot_relevant_experience' => 6.5,
                'snapshot_total_experience' => 7.5,
                'snapshot_current_ctc' => 2600000,
                'snapshot_expected_ctc' => 3300000,
                'snapshot_notice_period_days' => 30,
                'snapshot_location' => 'Bengaluru',
                'screening_summary' => 'Strong microservice architecture background.',
                'candidate_availability' => 'Flexible for afternoon video slots',
                'recruiter_recommendation' => 'Recommended for technical panel.',
                'client_decision' => 'shortlisted',
                'decided_by' => $clientUser1->id,
                'decided_at' => now()->subDay(),
            ]
        );

        InterviewRecord::firstOrCreate(
            ['candidate_application_id' => $app2->id, 'round_number' => 1],
            [
                'job_requirement_id' => $job1->id,
                'client_id' => $client1->id,
                'candidate_id' => $cand2->id,
                'round_name' => 'Round 1: HR Screening',
                'interviewer_name' => 'Meera Krishnan',
                'interview_date' => now()->subDay()->toDateString(),
                'interview_time' => '11:30',
                'scheduled_at' => now()->subDay()->setTime(11, 30),
                'duration_minutes' => 30,
                'mode' => 'telephonic',
                'candidate_confirmed' => true,
                'client_confirmed' => true,
                'status' => 'passed',
                'result' => 'passed',
                'score' => 8.5,
            ]
        );

        InterviewRecord::firstOrCreate(
            ['candidate_application_id' => $app2->id, 'round_number' => 2],
            [
                'job_requirement_id' => $job1->id,
                'client_id' => $client1->id,
                'candidate_id' => $cand2->id,
                'round_name' => 'Round 2: Technical Interview',
                'interviewer_name' => 'Karthik Subramanian',
                'interview_date' => now()->toDateString(),
                'interview_time' => '16:00',
                'scheduled_at' => now()->setTime(16, 0),
                'duration_minutes' => 60,
                'mode' => 'video',
                'meeting_link' => 'https://meet.orbitouch-hr.com/apex-r2-divya',
                'candidate_confirmed' => true,
                'client_confirmed' => true,
                'status' => 'scheduled',
            ]
        );

        Task::firstOrCreate(
            ['title' => 'Confirm Round 2 Technical Interview readiness with Divya Menon'],
            [
                'task_type' => 'confirm_interview',
                'assigned_to' => $recruiter1->id,
                'created_by' => $teamLeader->id,
                'candidate_id' => $cand2->id,
                'client_id' => $client1->id,
                'job_requirement_id' => $job1->id,
                'candidate_application_id' => $app2->id,
                'priority' => 'high',
                'due_date' => now()->toDateString(),
                'due_time' => '14:30',
                'status' => 'pending',
            ]
        );
    }
}
