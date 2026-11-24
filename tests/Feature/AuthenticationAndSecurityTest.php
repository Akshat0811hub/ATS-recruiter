<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Client;
use App\Models\ClientSubmission;
use App\Models\JobRequirement;
use App\Models\ResumeVersion;
use App\Models\Role;
use App\Models\StorageRecord;
use App\Models\User;
use App\Services\TwoFactorService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthenticationAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new DatabaseSeeder)->seedCoreFoundation();
    }

    public function test_user_can_login_and_login_activity_is_recorded(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@orbitouch-hr.com',
            'password' => 'OrbiTouch@2026!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('login_activities', [
            'email' => 'admin@orbitouch-hr.com',
            'event_type' => 'login_success',
        ]);
    }

    public function test_deactivated_account_cannot_login(): void
    {
        $admin = User::where('email', 'admin@orbitouch-hr.com')->firstOrFail();
        $admin->update(['is_active' => false]);

        $response = $this->post('/login', [
            'email' => 'admin@orbitouch-hr.com',
            'password' => 'OrbiTouch@2026!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_two_factor_authentication_challenge_is_enforced_when_enabled(): void
    {
        $twoFactor = new TwoFactorService;
        $secret = $twoFactor->generateSecret();

        $admin = User::where('email', 'admin@orbitouch-hr.com')->firstOrFail();
        $admin->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
        ]);

        $loginRes = $this->post('/login', [
            'email' => 'admin@orbitouch-hr.com',
            'password' => 'OrbiTouch@2026!',
        ]);
        $loginRes->assertRedirect('/two-factor/challenge');

        // Accessing dashboard before verifying 2FA redirects back to challenge
        $this->get('/dashboard')->assertRedirect('/two-factor/challenge');

        $validCode = $twoFactor->getCurrentTotpCode($secret);
        $verifyRes = $this->post('/two-factor/challenge', ['code' => $validCode]);
        $verifyRes->assertRedirect('/dashboard');
    }

    /**
     * Scenario 18: An unauthorised recruiter attempts to access/modify another user's restricted data.
     */
    public function test_scenario_18_unauthorised_recruiter_cannot_access_or_modify_restricted_data(): void
    {
        $recruiterRole = Role::where('slug', 'recruiter')->firstOrFail();
        $recruiterA = User::create([
            'employee_code' => 'REC-A',
            'role_id' => $recruiterRole->id,
            'name' => 'Recruiter A',
            'email' => 'reca@orbitouch-hr.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $recruiterB = User::create([
            'employee_code' => 'REC-B',
            'role_id' => $recruiterRole->id,
            'name' => 'Recruiter B',
            'email' => 'recb@orbitouch-hr.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);

        $candidateOwnedByA = Candidate::create([
            'full_name' => 'Protected Candidate',
            'mobile_number' => '+91 9810098100',
            'assigned_recruiter_id' => $recruiterA->id,
        ]);

        // Recruiter B attempts to update Recruiter A's candidate
        $this->actingAs($recruiterB)
            ->put("/candidates/{$candidateOwnedByA->id}", [
                'full_name' => 'Tampered Name',
                'mobile_number' => '+91 9810098100',
            ])
            ->assertStatus(403);

        // Recruiter B attempts to access Super Admin User Management
        $this->actingAs($recruiterB)
            ->get('/admin/users')
            ->assertStatus(403);
    }

    /**
     * Scenario 19: One client attempts to access another client's documents.
     */
    public function test_scenario_19_client_cannot_access_another_clients_confidential_documents(): void
    {
        Storage::fake('local');

        $clientRole = Role::where('slug', 'client')->firstOrFail();
        $clientOrgA = Client::create([
            'company_name' => 'Client Alpha Corp',
            'primary_hr_name' => 'Alpha HR',
            'primary_hr_phone' => '9800000001',
            'primary_hr_email' => 'hr@alphacorp.com',
        ]);
        $clientOrgB = Client::create([
            'company_name' => 'Client Beta Corp',
            'primary_hr_name' => 'Beta HR',
            'primary_hr_phone' => '9800000002',
            'primary_hr_email' => 'hr@betacorp.com',
        ]);

        $clientUserA = User::create([
            'role_id' => $clientRole->id,
            'client_id' => $clientOrgA->id,
            'name' => 'Alpha HR User',
            'email' => 'hr@alphacorp.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);

        $clientUserB = User::create([
            'role_id' => $clientRole->id,
            'client_id' => $clientOrgB->id,
            'name' => 'Beta HR User',
            'email' => 'hr@betacorp.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);

        Storage::disk('local')->put('vault/resume/alpha_candidate.pdf', 'Confidential PDF Content');
        $storageRec = StorageRecord::create([
            'disk' => 'local',
            'category' => 'resume',
            'file_path' => 'vault/resume/alpha_candidate.pdf',
            'secure_filename' => 'alpha_candidate.pdf',
            'original_name' => 'alpha_candidate.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 24,
            'sha256_checksum' => hash('sha256', 'Confidential PDF Content'),
        ]);

        $cand = Candidate::create([
            'full_name' => 'Alpha Submitted Candidate',
            'mobile_number' => '9876500001',
        ]);
        $rv = ResumeVersion::create([
            'candidate_id' => $cand->id,
            'version_number' => 1,
            'storage_record_id' => $storageRec->id,
            'original_filename' => 'alpha_candidate.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 24,
            'sha256_checksum' => $storageRec->sha256_checksum,
            'is_primary' => true,
        ]);

        $jobA = JobRequirement::create([
            'client_id' => $clientOrgA->id,
            'title' => 'Alpha Architect',
            'openings_count' => 1,
            'location' => 'Mumbai',
        ]);
        $appA = $cand->applications()->create([
            'job_requirement_id' => $jobA->id,
            'client_id' => $clientOrgA->id,
            'pipeline_stage' => 'submitted',
        ]);
        ClientSubmission::create([
            'candidate_application_id' => $appA->id,
            'job_requirement_id' => $jobA->id,
            'client_id' => $clientOrgA->id,
            'candidate_id' => $cand->id,
            'resume_version_id' => $rv->id,
            'snapshot_candidate_name' => $cand->full_name,
            'screening_summary' => 'Submitted to Alpha Corp only',
        ]);

        // Client A can download their submitted candidate's resume
        $this->actingAs($clientUserA)
            ->get("/documents/{$storageRec->id}/download")
            ->assertStatus(200);

        // Client B is blocked with 403 Forbidden from accessing Client A's resume
        $this->actingAs($clientUserB)
            ->get("/documents/{$storageRec->id}/download")
            ->assertStatus(403);
    }

    /**
     * Scenario 28: A browser session expires during data entry.
     */
    public function test_scenario_28_expired_session_redirects_safely_to_login(): void
    {
        $response = $this->post('/candidates', [
            'full_name' => 'Unauthenticated Entry',
            'mobile_number' => '9811111111',
        ]);

        $response->assertRedirect('/login');
    }
}
