<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\Client;
use App\Models\ClientSubmission;
use App\Models\InterviewRecord;
use App\Models\JobRequirement;
use App\Models\JoiningRecord;
use App\Models\Role;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecruitmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $recruiter1;

    protected User $recruiter2;

    protected Client $client;

    protected User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        (new DatabaseSeeder)->seedCoreFoundation();

        $this->superAdmin = User::where('email', 'admin@orbitouch-hr.com')->firstOrFail();
        $recruiterRole = Role::where('slug', 'recruiter')->firstOrFail();
        $clientRole = Role::where('slug', 'client')->firstOrFail();

        $this->recruiter1 = User::create([
            'employee_code' => 'ORB-REC-101',
            'role_id' => $recruiterRole->id,
            'name' => 'Recruiter One',
            'email' => 'rec1@orbitouch-hr.com',
            'password' => Hash::make('OrbiTouch@2026!'),
            'is_active' => true,
        ]);

        $this->recruiter2 = User::create([
            'employee_code' => 'ORB-REC-102',
            'role_id' => $recruiterRole->id,
            'name' => 'Recruiter Two',
            'email' => 'rec2@orbitouch-hr.com',
            'password' => Hash::make('OrbiTouch@2026!'),
            'is_active' => true,
        ]);

        $this->client = Client::create([
            'company_name' => 'Zenith Enterprise FinTech Ltd',
            'industry' => 'FinTech',
            'primary_hr_name' => 'Kavita Nair',
            'primary_hr_phone' => '+91 9820055001',
            'primary_hr_email' => 'kavita@zenithfintech.com',
            'placement_fee_percentage' => 10.0,
            'replacement_period_days' => 90,
        ]);

        $this->clientUser = User::create([
            'employee_code' => 'CLT-ZEN-01',
            'role_id' => $clientRole->id,
            'client_id' => $this->client->id,
            'name' => 'Kavita Nair',
            'email' => 'kavita@zenithfintech.com',
            'password' => Hash::make('OrbiTouch@2026!'),
            'is_active' => true,
        ]);
    }

    /**
     * Scenarios 1 to 14: Complete End-to-End Recruitment Lifecycle from Client Requirement to Placement Closure.
     */
    public function test_scenarios_1_to_14_complete_end_to_end_recruitment_lifecycle(): void
    {
        // 1. Client creates two separate job requirements
        $this->actingAs($this->clientUser)->post('/portal/requirements', [
            'title' => 'Principal Distributed Systems Engineer',
            'openings_count' => 2,
            'location' => 'Mumbai',
            'work_mode' => 'hybrid',
            'min_experience' => 5,
            'interview_rounds_count' => 5,
            'description' => 'Lead core payment ledger engineering.',
        ])->assertRedirect('/portal/dashboard');

        $this->actingAs($this->clientUser)->post('/portal/requirements', [
            'title' => 'VP - Cybersecurity & Compliance',
            'openings_count' => 1,
            'location' => 'Bengaluru',
            'work_mode' => 'onsite',
            'min_experience' => 10,
            'interview_rounds_count' => 3,
            'description' => 'Head enterprise security.',
        ])->assertRedirect('/portal/dashboard');

        $this->assertEquals(2, JobRequirement::where('client_id', $this->client->id)->count());
        $job1 = JobRequirement::where('title', 'Principal Distributed Systems Engineer')->firstOrFail();
        $this->assertEquals(2, $job1->openings_count);
        $this->assertEquals(2, $job1->remaining_count);

        // 2. Admin assigns two recruiters to one requirement
        $this->actingAs($this->superAdmin)->post("/jobs/{$job1->id}/assign", [
            'primary_recruiter_id' => $this->recruiter1->id,
            'secondary_recruiter_ids' => [$this->recruiter2->id],
            'daily_sourcing_target' => 8,
            'submission_target' => 5,
            'priority' => 'critical',
        ])->assertSessionHasNoErrors();

        $this->assertEquals(2, $job1->assignments()->count());

        // 3 & 5. Both recruiters source candidates with private resume uploads
        $resumePdf1 = UploadedFile::fake()->createWithContent('rohit_cv.pdf', '%PDF-1.4 Rohit Sharma Resume Content');
        $this->actingAs($this->recruiter1)->post('/candidates', [
            'full_name' => 'Rohit Sharma',
            'mobile_number' => '+91 9820111222',
            'email' => 'rohit.sharma@example.com',
            'total_experience' => 7.0,
            'relevant_experience' => 6.0,
            'current_ctc' => 2200000,
            'expected_ctc' => 3000000,
            'notice_period_days' => 15,
            'job_requirement_id' => $job1->id,
            'resume_file' => $resumePdf1,
        ])->assertSessionHasNoErrors();

        $resumePdf2 = UploadedFile::fake()->createWithContent('sneha_cv.pdf', '%PDF-1.4 Sneha Patel Resume Content');
        $this->actingAs($this->recruiter2)->post('/candidates', [
            'full_name' => 'Sneha Patel',
            'mobile_number' => '+91 9820111333',
            'email' => 'sneha.patel@example.com',
            'total_experience' => 6.5,
            'relevant_experience' => 5.5,
            'current_ctc' => 2000000,
            'expected_ctc' => 2800000,
            'notice_period_days' => 30,
            'job_requirement_id' => $job1->id,
            'resume_file' => $resumePdf2,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->recruiter1)->post('/candidates', [
            'full_name' => 'Vikramaditya Joshi',
            'mobile_number' => '+91 9820111444',
            'email' => 'vikram.joshi@example.com',
            'total_experience' => 8.0,
            'relevant_experience' => 7.0,
            'current_ctc' => 2400000,
            'expected_ctc' => 3200000,
            'notice_period_days' => 30,
            'job_requirement_id' => $job1->id,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(3, Candidate::count());
        $cand1 = Candidate::where('email', 'rohit.sharma@example.com')->firstOrFail();
        $this->assertNotNull($cand1->primaryResume);

        // 4. Duplicate candidates are detected correctly
        $dupCheckRes = $this->actingAs($this->recruiter2)->post('/candidates', [
            'full_name' => 'Rohit Sharma Duplicate',
            'mobile_number' => '9820111222', // Normalized match with +91 9820111222
            'email' => 'rohit.other@example.com',
        ]);
        $dupCheckRes->assertSessionHas('duplicate_warning');
        $this->assertEquals(3, Candidate::count());

        // 6. Recruiter records call discussions
        $app1 = CandidateApplication::where('candidate_id', $cand1->id)->where('job_requirement_id', $job1->id)->firstOrFail();
        $this->actingAs($this->recruiter1)->post("/candidates/{$cand1->id}/calls", [
            'job_requirement_id' => $job1->id,
            'candidate_application_id' => $app1->id,
            'outcome' => 'screening_completed',
            'duration_seconds' => 600,
            'discussion_summary' => 'Candidate has 6 yrs distributed systems experience and 15 days notice.',
        ])->assertSessionHasNoErrors();

        $this->assertEquals(1, $cand1->callLogs()->count());

        // 7. Three profiles are submitted to the client
        foreach (CandidateApplication::where('job_requirement_id', $job1->id)->get() as $application) {
            $this->actingAs($this->recruiter1)->post("/applications/{$application->id}/submit", [
                'screening_summary' => "Screened {$application->candidate->full_name} for {$job1->title}",
                'recruiter_recommendation' => 'Recommended',
            ])->assertSessionHasNoErrors();
        }
        $this->assertEquals(3, ClientSubmission::where('job_requirement_id', $job1->id)->count());

        // Verify duplicate submission for same candidate & job is prevented
        $this->actingAs($this->recruiter1)->post("/applications/{$app1->id}/submit", [
            'screening_summary' => 'Duplicate submission attempt',
        ])->assertSessionHasErrors('duplicate_submission');

        // 8. Client shortlists one profile
        $sub1 = ClientSubmission::where('candidate_application_id', $app1->id)->firstOrFail();
        $this->actingAs($this->clientUser)->post("/portal/submissions/{$sub1->id}/decision", [
            'client_decision' => 'shortlisted',
            'client_feedback_notes' => 'Strong profile, proceed with 5 interview rounds.',
        ])->assertSessionHasNoErrors();

        $this->assertEquals('client_shortlisted', $app1->fresh()->pipeline_stage);

        // 9 & 10. Recruiter schedules five interview rounds and candidate passes all five rounds
        for ($round = 1; $round <= 5; $round++) {
            $this->actingAs($this->recruiter1)->post("/applications/{$app1->id}/interviews", [
                'round_number' => $round,
                'round_name' => "Round {$round}: Evaluation Stage",
                'interviewer_name' => "Zenith Panelist {$round}",
                'interview_date' => now()->addDays($round)->toDateString(),
                'interview_time' => '11:00',
                'duration_minutes' => 45,
                'mode' => 'video',
            ])->assertSessionHasNoErrors();

            $interviewRec = InterviewRecord::where('candidate_application_id', $app1->id)
                ->where('round_number', $round)
                ->firstOrFail();

            $this->actingAs($this->recruiter1)->post("/interviews/{$interviewRec->id}/result", [
                'status' => 'passed',
                'score' => 9.0,
                'feedback_summary' => "Cleared Round {$round} with distinction.",
            ])->assertSessionHasNoErrors();
        }

        $this->assertEquals(5, $app1->interviewRecords()->where('status', 'passed')->count());
        $this->assertEquals('selected', $app1->fresh()->pipeline_stage);

        // 11. Candidate receives and accepts an offer
        $this->actingAs($this->recruiter1)->post("/applications/{$app1->id}/offer", [
            'selection_date' => now()->toDateString(),
            'offered_designation' => 'Principal Distributed Systems Engineer',
            'offered_salary' => 2900000,
            'negotiated_salary' => 3000000,
            'currency' => 'INR',
            'expected_joining_date' => now()->addDays(10)->toDateString(),
            'status' => 'offer_accepted',
        ])->assertSessionHasNoErrors();

        $this->assertEquals('offer_accepted', $app1->fresh()->pipeline_stage);
        $joining1 = JoiningRecord::where('candidate_application_id', $app1->id)->firstOrFail();

        // 12 & 13. Candidate joins and vacancy counts update correctly (2 required -> 1 joined -> 1 remaining)
        $this->actingAs($this->recruiter1)->post("/joinings/{$joining1->id}/confirm", [
            'actual_joining_date' => now()->toDateString(),
            'final_salary' => 3000000,
            'candidate_confirmed' => true,
            'client_confirmed' => true,
            'remarks' => 'Onboarded at Zenith Mumbai office.',
        ])->assertSessionHasNoErrors();

        $job1->refresh();
        $this->assertEquals(2, $job1->openings_count);
        $this->assertEquals(1, $job1->joined_count);
        $this->assertEquals(1, $job1->remaining_count);
        $this->assertEquals('partially_filled', $job1->status);

        // 14. The dashboard reflects accurate results
        $analytics = app(DashboardAnalyticsService::class)->getExecutiveAnalytics();
        $this->assertEquals(3, $analytics['overview']['total_candidates']);
        $this->assertEquals(3, $analytics['overview']['total_submissions']);
        $this->assertEquals(1, $analytics['overview']['client_shortlists']);
        $this->assertEquals(1, $analytics['overview']['candidates_joined']);
        $this->assertEquals(1, $analytics['overview']['filled_vacancies']);
    }

    /**
     * Scenarios 15, 16, 17, 21, 22, 23: Edge cases, failed rounds, reschedules, concurrency locks, non-joining, and replacements.
     */
    public function test_scenarios_15_16_17_21_22_23_advanced_workflows_and_concurrency(): void
    {
        $jobA = JobRequirement::create([
            'client_id' => $this->client->id,
            'title' => 'Data Engineering Lead',
            'openings_count' => 1,
            'location' => 'Mumbai',
            'interview_rounds_count' => 3,
        ]);
        $jobB = JobRequirement::create([
            'client_id' => $this->client->id,
            'title' => 'Cloud Platform Architect',
            'openings_count' => 1,
            'location' => 'Pune',
            'interview_rounds_count' => 3,
        ]);

        $cand = Candidate::create([
            'full_name' => 'Arjun Kapoor',
            'mobile_number' => '+91 9833344455',
            'email' => 'arjun.kapoor@example.com',
            'assigned_recruiter_id' => $this->recruiter1->id,
        ]);

        // Scenario 21: A candidate is considered for multiple jobs without overwriting history
        $appA = CandidateApplication::create([
            'candidate_id' => $cand->id,
            'job_requirement_id' => $jobA->id,
            'client_id' => $this->client->id,
            'recruiter_id' => $this->recruiter1->id,
            'pipeline_stage' => 'client_shortlisted',
        ]);
        $appB = CandidateApplication::create([
            'candidate_id' => $cand->id,
            'job_requirement_id' => $jobB->id,
            'client_id' => $this->client->id,
            'recruiter_id' => $this->recruiter1->id,
            'pipeline_stage' => 'client_shortlisted',
        ]);
        $this->assertEquals(2, $cand->applications()->count());

        // Scenario 16: An interview is rescheduled multiple times, preserving attempt history
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->actingAs($this->recruiter1)->post("/applications/{$appA->id}/interviews", [
                'round_number' => 1,
                'round_name' => 'Round 1: Technical Screening',
                'interviewer_name' => 'Panel Lead',
                'interview_date' => now()->addDays($attempt)->toDateString(),
                'interview_time' => '14:00',
                'duration_minutes' => 45,
                'mode' => 'video',
                'reschedule_reason' => "Schedule attempt #{$attempt}",
            ])->assertSessionHasNoErrors();
        }

        $intR1 = InterviewRecord::where('candidate_application_id', $appA->id)->where('round_number', 1)->firstOrFail();
        $this->assertEquals(3, $intR1->attempts_count);
        $this->assertEquals(3, $intR1->attempts()->count());

        // Pass Round 1, then Scenario 15: Fail Round 2 and retain Round 1 information
        $this->actingAs($this->recruiter1)->post("/interviews/{$intR1->id}/result", [
            'status' => 'passed',
            'score' => 8.0,
            'feedback_summary' => 'Cleared Round 1',
        ]);

        $this->actingAs($this->recruiter1)->post("/applications/{$appA->id}/interviews", [
            'round_number' => 2,
            'round_name' => 'Round 2: Deep Dive',
            'interviewer_name' => 'CTO',
            'interview_date' => now()->addDays(5)->toDateString(),
            'interview_time' => '16:00',
            'duration_minutes' => 60,
            'mode' => 'video',
        ]);

        $intR2 = InterviewRecord::where('candidate_application_id', $appA->id)->where('round_number', 2)->firstOrFail();
        $this->actingAs($this->recruiter1)->post("/interviews/{$intR2->id}/result", [
            'status' => 'failed',
            'score' => 4.0,
            'feedback_summary' => 'Did not meet distributed transaction requirements.',
        ]);

        // Verify Round 1 passed data is retained while Round 2 is failed
        $this->assertEquals('passed', $intR1->fresh()->status);
        $this->assertEquals('failed', $intR2->fresh()->status);

        // Verify unauthorised progression to Round 3 is blocked after failing Round 2
        $this->actingAs($this->recruiter1)->post("/applications/{$appA->id}/interviews", [
            'round_number' => 3,
            'round_name' => 'Round 3: HR',
            'interviewer_name' => 'HR Head',
            'interview_date' => now()->addDays(6)->toDateString(),
            'interview_time' => '12:00',
            'duration_minutes' => 30,
            'mode' => 'video',
        ])->assertSessionHasErrors('interview_progression');

        // Scenario 17: Two users attempt to update the same record (stale lock_version rejected)
        $staleLockVersion = $appB->lock_version;
        $this->actingAs($this->recruiter1)->postJson("/applications/{$appB->id}/transition", [
            'target_stage' => 'interview_scheduled',
            'lock_version' => $staleLockVersion,
        ])->assertStatus(200);

        // Second user sends the old stale lock_version
        $this->actingAs($this->recruiter2)->postJson("/applications/{$appB->id}/transition", [
            'target_stage' => 'selected',
            'lock_version' => $staleLockVersion,
        ])->assertStatus(409);

        // Scenario 22: Candidate accepts an offer on Job B but does not join
        $this->actingAs($this->recruiter1)->post("/applications/{$appB->id}/offer", [
            'selection_date' => now()->toDateString(),
            'offered_designation' => 'Cloud Platform Architect',
            'offered_salary' => 3200000,
            'currency' => 'INR',
            'expected_joining_date' => now()->addDays(7)->toDateString(),
            'status' => 'offer_accepted',
        ]);

        $joiningB = JoiningRecord::where('candidate_application_id', $appB->id)->firstOrFail();
        $this->actingAs($this->recruiter1)->post("/joinings/{$joiningB->id}/did-not-join", [
            'non_joining_reason' => 'Accepted counter-offer from current employer',
        ])->assertSessionHasNoErrors();

        $this->assertEquals('did_not_join', $joiningB->fresh()->status);
        $this->assertEquals(1, $jobB->fresh()->remaining_count);

        // Scenario 23: Candidate joins and then leaves within replacement period -> Replacement vacancy is reopened
        $this->actingAs($this->recruiter1)->post("/joinings/{$joiningB->id}/confirm", [
            'actual_joining_date' => now()->toDateString(),
            'final_salary' => 3200000,
        ]);
        $this->assertEquals(0, $jobB->fresh()->remaining_count);
        $this->assertEquals('successfully_closed', $jobB->fresh()->status);

        $this->actingAs($this->superAdmin)->post("/joinings/{$joiningB->id}/replacement", [
            'exit_date' => now()->addDays(20)->toDateString(),
            'exit_reason' => 'Candidate relocated due to personal emergency within 90-day warranty',
            'reopen_vacancy' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(1, $jobB->fresh()->remaining_count);
        $this->assertNotEquals('successfully_closed', $jobB->fresh()->status);
    }
}
