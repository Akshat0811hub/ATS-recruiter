<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Client;
use App\Models\JobRequirement;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_internal_admin_and_recruiter_pages_render_without_errors(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::where('email', 'admin@orbitouch-hr.com')->firstOrFail();
        $client = Client::firstOrFail();
        $job = JobRequirement::firstOrFail();
        $candidate = Candidate::firstOrFail();

        $internalRoutes = [
            '/dashboard',
            '/my-work',
            '/calendar',
            '/tasks',
            '/clients',
            "/clients/{$client->id}",
            '/jobs',
            "/jobs/{$job->id}",
            '/candidates',
            "/candidates/{$candidate->id}",
            "/candidates/{$candidate->id}/preview",
            '/candidates/import-template',
            '/candidates/export',
            '/applications',
            '/pipeline',
            '/pipeline?view=table',
            '/submissions',
            '/interviews',
            '/offers',
            '/joinings',
            '/management/recruiter-performance',
            '/management/client-performance',
            '/reports',
            '/reports/export?report_type=daily_recruiter_activity',
            '/notifications',
            '/profile',
            '/global-search?q=Aarav',
            '/admin/users',
            '/admin/roles',
            '/admin/teams',
            '/admin/settings',
            '/admin/email-templates',
            '/admin/custom-fields',
            '/admin/audit-logs',
            '/admin/storage',
            '/admin/backups',
            '/admin/recovery',
            '/admin/system-health',
        ];

        foreach ($internalRoutes as $uri) {
            $this->actingAs($superAdmin)
                ->get($uri)
                ->assertStatus(200);
        }
    }

    public function test_client_portal_pages_render_and_block_internal_crm_routes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $clientUser = User::where('email', 'client@apexfintech.example.com')->firstOrFail();

        $this->actingAs($clientUser)->get('/portal/dashboard')->assertStatus(200);
        $this->actingAs($clientUser)->get('/portal/compare')->assertStatus(200);
        $this->actingAs($clientUser)->get('/global-search?q=Lead')->assertStatus(200);

        // Client user must be blocked from internal staff routes
        $this->actingAs($clientUser)->get('/candidates')->assertStatus(403);
        $this->actingAs($clientUser)->get('/admin/users')->assertStatus(403);
    }
}
