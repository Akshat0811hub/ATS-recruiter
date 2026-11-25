<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\Client;
use App\Models\ClientSubmission;
use App\Models\JobAssignment;
use App\Models\JobRequirement;
use App\Models\ResumeVersion;
use App\Models\Role;
use App\Models\StorageRecord;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\TwoFactorService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $manager;

    protected User $recruiter1;

    protected User $recruiter2;

    protected Client $clientA;

    protected Client $clientB;

    protected User $clientUserA;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        (new DatabaseSeeder)->seedCoreFoundation();

        $this->superAdmin = User::where('email', 'admin@orbitouch-hr.com')->firstOrFail();
        $managerRole = Role::where('slug', 'manager')->firstOrFail();
        $recruiterRole = Role::where('slug', 'recruiter')->firstOrFail();
        $clientRole = Role::where('slug', 'client')->firstOrFail();

        $this->manager = User::create([
            'employee_code' => 'ORB-MGR-201',
            'role_id' => $managerRole->id,
            'name' => 'Operations Manager',
            'email' => 'ops.mgr@orbitouch-hr.com',
            'password' => Hash::make('OrbiTouch@2026!'),
            'is_active' => true,
        ]);

        $team = Team::create([
            'code' => 'ORB-TM-201',
            'name' => 'Enterprise Delivery Team',
            'manager_id' => $this->manager->id,
            'is_active' => true,
        ]);

        $this->recruiter1 = User::create([
            'employee_code' => 'ORB-REC-201',
            'role_id' => $recruiterRole->id,
            'team_id' => $team->id,
            'name' => 'Priya Recruiter',
            'email' => 'priya@orbitouch-hr.com',
            'password' => Hash::make('OrbiTouch@2026!'),
            'is_active' => true,
        ]);

        $this->recruiter2 = User::create([
            'employee_code' => 'ORB-REC-202',
            'role_id' => $recruiterRole->id,
            'team_id' => $team->id,
            'name' => 'Karan Recruiter',
            'email' => 'karan@orbitouch-hr.com',
            'password' => Hash::make('OrbiTouch@2026!'),
            'is_active' => true,
        ]);

        $this->clientA = Client::create([
            'company_name' => 'Horizon Cloud Systems',
            'industry' => 'SaaS',
            'primary_hr_name' => 'Meera Iyer',
            'primary_hr_phone' => '+91 9821000111',
            'primary_hr_email' => 'meera@horizoncloud.example.com',
        ]);

        $this->clientB = Client::create([
            'company_name' => 'crestline Logistics Ltd',
            'industry' => 'Logistics',
            'primary_hr_name' => 'Rohan Desai',
            'primary_hr_phone' => '+91 9821000222',
            'primary_hr_email' => 'rohan@crestline.example.com',
        ]);

        $this->clientUserA = User::create([
            'employee_code' => 'CLT-HRZ-01',
            'role_id' => $clientRole->id,
            'client_id' => $this->clientA->id,
            'name' => 'Meera Iyer',
            'email' => 'meera@horizoncloud.example.com',
            'password' => Hash::make('OrbiTouch@2026!'),
            'is_active' => true,
        ]);
    }

    public function test_candidate_can_be_saved_updated_and_submitted_with_optional_fields_left_blank_and_defaults_to_pending_consent(): void
    {
        $job = JobRequirement::create([
            'client_id' => $this->clientA->id,
            'title' => 'Backend Laravel Engineer',
            'openings_count' => 1,
            'location' => 'Mumbai',
        ]);

        JobAssignment::create([
            'job_requirement_id' => $job->id,
            'recruiter_id' => $this->recruiter1->id,
            'assigned_by' => $this->superAdmin->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        $this->actingAs($this->recruiter1)->post('/candidates', [
            'full_name' => 'Minimal Candidate',
            'mobile_number' => '+91 9811223344',
            'email' => '',
            'current_location' => '',
            'preferred_location' => '',
            'total_experience' => '',
            'relevant_experience' => '',
            'current_ctc' => '',
            'expected_ctc' => '',
            'notice_period_days' => '',
            'job_requirement_id' => $job->id,
        ])->assertSessionHasNoErrors();

        $candidate = Candidate::where('full_name', 'Minimal Candidate')->firstOrFail();
        $this->assertNull($candidate->notice_period_days);
        $this->assertNull($candidate->current_location);
        $this->assertNull($candidate->current_ctc);
        $this->assertNull($candidate->expected_ctc);
        $this->assertSame('pending', $candidate->consent_status);
        $this->assertNull($candidate->consent_recorded_at);

        // Update candidate keeping optional fields blank and then granting consent
        $this->actingAs($this->recruiter1)->put("/candidates/{$candidate->id}", [
            'full_name' => 'Minimal Candidate Updated',
            'mobile_number' => '+91 9811223344',
            'notice_period_days' => '',
            'total_experience' => '',
            'consent_status' => 'granted',
        ])->assertSessionHasNoErrors();

        $candidate->refresh();
        $this->assertSame('Minimal Candidate Updated', $candidate->full_name);
        $this->assertNull($candidate->notice_period_days);
        $this->assertSame('granted', $candidate->consent_status);
        $this->assertNotNull($candidate->consent_recorded_at);

        // Candidate index and show pages render cleanly without "d notice" formatting glitches
        $this->actingAs($this->recruiter1)
            ->get('/candidates')
            ->assertOk()
            ->assertSee('Notice N/A');

        $this->actingAs($this->recruiter1)
            ->get("/candidates/{$candidate->id}")
            ->assertOk()
            ->assertSee('Not provided');

        // Submitting candidate with blank optional fields succeeds cleanly
        $application = CandidateApplication::where('candidate_id', $candidate->id)->firstOrFail();
        $this->actingAs($this->recruiter1)->post("/applications/{$application->id}/submit", [
            'screening_summary' => 'Strong backend fundamentals; notice period to be confirmed.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('client_submissions', [
            'candidate_application_id' => $application->id,
            'snapshot_notice_period_days' => null,
        ]);
    }

    public function test_reports_apply_date_recruiter_client_and_job_filters_identically_to_ui_and_csv_export(): void
    {
        $jobA = JobRequirement::create([
            'client_id' => $this->clientA->id,
            'title' => 'Horizon DevOps Engineer',
            'openings_count' => 2,
            'location' => 'Mumbai',
        ]);
        $jobB = JobRequirement::create([
            'client_id' => $this->clientB->id,
            'title' => 'Crestline QA Lead',
            'openings_count' => 1,
            'location' => 'Pune',
        ]);

        $candA = Candidate::create([
            'full_name' => 'Filtered Match Candidate',
            'mobile_number' => '9870001111',
            'assigned_recruiter_id' => $this->recruiter1->id,
        ]);
        $candB = Candidate::create([
            'full_name' => 'Excluded Other Candidate',
            'mobile_number' => '9870002222',
            'assigned_recruiter_id' => $this->recruiter2->id,
        ]);

        $appA = CandidateApplication::create([
            'candidate_id' => $candA->id,
            'job_requirement_id' => $jobA->id,
            'client_id' => $this->clientA->id,
            'recruiter_id' => $this->recruiter1->id,
            'pipeline_stage' => 'submitted',
        ]);
        $appB = CandidateApplication::create([
            'candidate_id' => $candB->id,
            'job_requirement_id' => $jobB->id,
            'client_id' => $this->clientB->id,
            'recruiter_id' => $this->recruiter2->id,
            'pipeline_stage' => 'submitted',
        ]);

        ClientSubmission::create([
            'candidate_application_id' => $appA->id,
            'job_requirement_id' => $jobA->id,
            'client_id' => $this->clientA->id,
            'candidate_id' => $candA->id,
            'recruiter_id' => $this->recruiter1->id,
            'snapshot_candidate_name' => $candA->full_name,
            'screening_summary' => 'Matched filter submission',
            'client_decision' => 'shortlisted',
            'submitted_at' => '2026-06-15 10:00:00',
        ]);

        ClientSubmission::create([
            'candidate_application_id' => $appB->id,
            'job_requirement_id' => $jobB->id,
            'client_id' => $this->clientB->id,
            'candidate_id' => $candB->id,
            'recruiter_id' => $this->recruiter2->id,
            'snapshot_candidate_name' => $candB->full_name,
            'screening_summary' => 'Outside filter submission',
            'client_decision' => 'pending_review',
            'submitted_at' => '2026-05-01 10:00:00',
        ]);

        $query = [
            'report_type' => 'profile_submissions',
            'from_date' => '2026-06-01',
            'to_date' => '2026-06-30',
            'recruiter_id' => $this->recruiter1->id,
            'client_id' => $this->clientA->id,
            'job_id' => $jobA->id,
            'status' => 'shortlisted',
        ];

        $pageRes = $this->actingAs($this->manager)->get('/reports?'.http_build_query($query));
        $pageRes->assertOk()
            ->assertSee('Filtered Match Candidate')
            ->assertDontSee('Excluded Other Candidate');

        $csvRes = $this->actingAs($this->manager)->get('/reports/export?'.http_build_query($query));
        $csvRes->assertOk();
        $csvBody = $csvRes->getContent();
        $this->assertStringContainsString('Filtered Match Candidate', $csvBody);
        $this->assertStringNotContainsString('Excluded Other Candidate', $csvBody);
    }

    public function test_completing_recurring_task_multiple_times_does_not_create_duplicate_tasks(): void
    {
        $task = Task::create([
            'title' => 'Weekly Client Pipeline Sync',
            'task_type' => 'follow_up_client',
            'assigned_to' => $this->recruiter1->id,
            'created_by' => $this->recruiter1->id,
            'priority' => 'high',
            'due_date' => now()->toDateString(),
            'status' => 'pending',
            'is_recurring' => true,
            'recurrence_pattern' => 'weekly',
        ]);

        // First completion creates 1 next-occurrence task
        $this->actingAs($this->recruiter1)->post("/tasks/{$task->id}/complete")->assertRedirect();
        $this->assertEquals('completed', $task->fresh()->status);
        $this->assertEquals(2, Task::where('title', 'Weekly Client Pipeline Sync')->count());

        // Completing the same task a second and third time must NOT spawn duplicates
        $this->actingAs($this->recruiter1)->post("/tasks/{$task->id}/complete")->assertRedirect();
        $this->actingAs($this->recruiter1)->post("/tasks/{$task->id}/complete")->assertRedirect();

        $this->assertEquals(2, Task::where('title', 'Weekly Client Pipeline Sync')->count());
        $this->assertEquals(1, Task::where('title', 'Weekly Client Pipeline Sync')->where('status', 'pending')->count());
    }

    public function test_recruiter_and_client_data_access_and_resume_version_isolation(): void
    {
        $jobAssigned = JobRequirement::create([
            'client_id' => $this->clientA->id,
            'title' => 'Assigned Frontend Engineer',
            'openings_count' => 1,
            'location' => 'Mumbai',
        ]);
        JobAssignment::create([
            'job_requirement_id' => $jobAssigned->id,
            'recruiter_id' => $this->recruiter1->id,
            'assigned_by' => $this->superAdmin->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        $jobUnassigned = JobRequirement::create([
            'client_id' => $this->clientB->id,
            'title' => 'Restricted Secret Role',
            'openings_count' => 1,
            'location' => 'Bengaluru',
        ]);

        $candRec1 = Candidate::create([
            'full_name' => 'Priya Allowed Candidate',
            'mobile_number' => '9860001111',
            'assigned_recruiter_id' => $this->recruiter1->id,
        ]);
        $candRec2 = Candidate::create([
            'full_name' => 'Karan Private Candidate',
            'mobile_number' => '9860002222',
            'assigned_recruiter_id' => $this->recruiter2->id,
        ]);

        // Recruiter 1 sees allowed candidate & job, blocked from Recruiter 2's private candidate & unassigned job
        $this->actingAs($this->recruiter1)->get("/candidates/{$candRec1->id}")->assertOk();
        $this->actingAs($this->recruiter1)->get("/candidates/{$candRec2->id}")->assertForbidden();
        $this->actingAs($this->recruiter1)->get("/jobs/{$jobAssigned->id}")->assertOk();
        $this->actingAs($this->recruiter1)->get("/jobs/{$jobUnassigned->id}")->assertForbidden();

        // Create two resume versions on candRec1: v1 (submitted to Client A) and v2 (internal draft, NOT submitted)
        Storage::disk('local')->put('vault/resume/v1_shared.pdf', '%PDF-1.4 Shared v1');
        Storage::disk('local')->put('vault/resume/v2_internal.pdf', '%PDF-1.4 Internal v2');

        $recV1 = StorageRecord::create([
            'disk' => 'local',
            'category' => 'resume',
            'file_path' => 'vault/resume/v1_shared.pdf',
            'secure_filename' => 'v1_shared.pdf',
            'original_name' => 'v1_shared.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 18,
            'sha256_checksum' => hash('sha256', '%PDF-1.4 Shared v1'),
        ]);
        $recV2 = StorageRecord::create([
            'disk' => 'local',
            'category' => 'resume',
            'file_path' => 'vault/resume/v2_internal.pdf',
            'secure_filename' => 'v2_internal.pdf',
            'original_name' => 'v2_internal.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 20,
            'sha256_checksum' => hash('sha256', '%PDF-1.4 Internal v2'),
        ]);

        $rv1 = ResumeVersion::create([
            'candidate_id' => $candRec1->id,
            'version_number' => 1,
            'storage_record_id' => $recV1->id,
            'original_filename' => 'v1_shared.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 18,
            'sha256_checksum' => $recV1->sha256_checksum,
            'is_primary' => false,
        ]);
        ResumeVersion::create([
            'candidate_id' => $candRec1->id,
            'version_number' => 2,
            'storage_record_id' => $recV2->id,
            'original_filename' => 'v2_internal.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 20,
            'sha256_checksum' => $recV2->sha256_checksum,
            'is_primary' => true,
        ]);

        $app = CandidateApplication::create([
            'candidate_id' => $candRec1->id,
            'job_requirement_id' => $jobAssigned->id,
            'client_id' => $this->clientA->id,
            'recruiter_id' => $this->recruiter1->id,
            'pipeline_stage' => 'submitted',
        ]);

        ClientSubmission::create([
            'candidate_application_id' => $app->id,
            'job_requirement_id' => $jobAssigned->id,
            'client_id' => $this->clientA->id,
            'candidate_id' => $candRec1->id,
            'recruiter_id' => $this->recruiter1->id,
            'resume_version_id' => $rv1->id,
            'snapshot_candidate_name' => $candRec1->full_name,
            'screening_summary' => 'Shared v1 with Client A',
        ]);

        // Client A can download v1 (explicitly shared) but CANNOT download v2 (unsubmitted version)
        $this->actingAs($this->clientUserA)->get("/documents/{$recV1->id}/download")->assertOk();
        $this->actingAs($this->clientUserA)->get("/documents/{$recV2->id}/download")->assertForbidden();

        // Recruiter 2 (unassigned) cannot download candRec1's resume
        $this->actingAs($this->recruiter2)->get("/documents/{$recV1->id}/download")->assertForbidden();
    }

    public function test_csv_import_handles_bom_escaped_phones_validation_and_filtered_export(): void
    {
        $csvContent = "\xEF\xBB\xBFFull Name,Mobile Number,Email,Current Location,Total Experience,Notice Period Days,Skills\n"
            ."\"Ananya Rao\",\"'+91 9833001122\",ananya.rao@example.com,Bengaluru,5.5,30,\"Laravel, Vue\"\n"
            ."\"Bad Experience Row\",9833009999,bad@example.com,Mumbai,not_a_number,15,PHP\n";

        $file = UploadedFile::fake()->createWithContent('candidates.csv', $csvContent);

        $this->actingAs($this->manager)->post('/candidates/import', [
            '_form' => 'candidate_import',
            'import_file' => $file,
        ])->assertRedirect();

        $imported = Candidate::where('email', 'ananya.rao@example.com')->firstOrFail();
        $this->assertSame('+91 9833001122', $imported->mobile_number);
        $this->assertSame('9833001122', $imported->normalized_mobile);
        $this->assertSame('pending', $imported->consent_status);
        $this->assertNull($imported->consent_recorded_at);
        $this->assertDatabaseMissing('candidates', ['email' => 'bad@example.com']);

        // Filtered candidate export applies location filter
        Candidate::create([
            'full_name' => 'Delhi Candidate',
            'mobile_number' => '9844001122',
            'current_location' => 'Delhi',
            'assigned_recruiter_id' => $this->recruiter1->id,
        ]);

        $exportRes = $this->actingAs($this->manager)->get('/candidates/export?location=Bengaluru');
        $exportRes->assertOk();
        $this->assertStringContainsString('Ananya Rao', $exportRes->getContent());
        $this->assertStringNotContainsString('Delhi Candidate', $exportRes->getContent());
    }

    public function test_two_factor_setup_requires_code_verification_and_hides_secrets_after_activation(): void
    {
        $twoFactor = app(TwoFactorService::class);

        // Step 1: Initiate 2FA setup — must NOT enable 2FA yet
        $this->actingAs($this->manager)
            ->post('/profile/two-factor')
            ->assertRedirect();

        $this->manager->refresh();
        $this->assertFalse((bool) $this->manager->two_factor_enabled);
        $this->assertNull($this->manager->two_factor_secret);

        $pendingSecret = session('two_factor_setup_secret');
        $this->assertNotEmpty($pendingSecret);

        // Step 2: Submitting an invalid 6-digit code fails validation and keeps 2FA disabled
        $this->actingAs($this->manager)
            ->withSession(['two_factor_setup_secret' => $pendingSecret])
            ->post('/profile/two-factor/confirm', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->manager->refresh();
        $this->assertFalse((bool) $this->manager->two_factor_enabled);

        // Step 3: Submitting the valid TOTP code activates 2FA and flashes recovery codes once
        $validCode = $twoFactor->getCurrentTotpCode($pendingSecret);
        $confirmRes = $this->actingAs($this->manager)
            ->withSession(['two_factor_setup_secret' => $pendingSecret])
            ->post('/profile/two-factor/confirm', ['code' => $validCode]);

        $confirmRes->assertRedirect()->assertSessionHas('two_factor_recovery_codes_once');

        $this->manager->refresh();
        $this->assertTrue((bool) $this->manager->two_factor_enabled);
        $this->assertSame($pendingSecret, $this->manager->two_factor_secret);
        $this->assertNotNull($this->manager->two_factor_confirmed_at);

        $storedRecoveryCodes = json_decode((string) $this->manager->two_factor_recovery_codes, true);
        $this->assertCount(8, $storedRecoveryCodes);

        // Step 4: Immediate redirect visit shows the one-time recovery codes ONCE without exposing the secret key
        $firstVisit = $this->actingAs($this->manager)->get('/profile');

        $firstVisit->assertOk()
            ->assertSee('Active')
            ->assertSee($storedRecoveryCodes[0])
            ->assertDontSee($pendingSecret);

        // Step 5: Subsequent profile page visits must NOT leak the secret key, live TOTP code, or stored recovery codes
        $subsequentVisit = $this->actingAs($this->manager)->get('/profile');

        $subsequentVisit->assertOk()
            ->assertSee('Active')
            ->assertDontSee($pendingSecret)
            ->assertDontSee($storedRecoveryCodes[0]);
    }
}
