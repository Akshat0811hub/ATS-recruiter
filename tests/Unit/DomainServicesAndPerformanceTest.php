<?php

namespace Tests\Unit;

use App\Models\Candidate;
use App\Models\Client;
use App\Models\JobRequirement;
use App\Services\CandidateDuplicateService;
use App\Services\DashboardAnalyticsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainServicesAndPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new DatabaseSeeder)->seedCoreFoundation();
    }

    public function test_candidate_phone_and_email_normalization_and_safe_merge(): void
    {
        $this->assertEquals('9820123456', Candidate::normalizePhone('+91 (982) 012-3456'));
        $this->assertEquals('9820123456', Candidate::normalizePhone('09820123456'));
        $this->assertEquals('priya.nair@orbitouch-hr.com', Candidate::normalizeEmail('  PRIYA.NAIR@OrbiTouch-HR.com '));

        $master = Candidate::create([
            'full_name' => 'Master Profile',
            'mobile_number' => '+91 9820123456',
            'email' => 'master@example.com',
        ]);
        $duplicate = Candidate::create([
            'full_name' => 'Duplicate Profile',
            'mobile_number' => '9820123456',
            'email' => 'dup@example.com',
            'current_employer' => 'FinTech Global',
        ]);

        $client = Client::create([
            'company_name' => 'Merge Test Client',
            'primary_hr_name' => 'HR',
            'primary_hr_phone' => '9811111111',
            'primary_hr_email' => 'hr@mergetest.com',
        ]);
        $job = JobRequirement::create([
            'client_id' => $client->id,
            'title' => 'Backend Engineer',
            'openings_count' => 2,
            'location' => 'Mumbai',
        ]);
        $duplicate->applications()->create([
            'job_requirement_id' => $job->id,
            'client_id' => $client->id,
            'pipeline_stage' => 'screened',
        ]);

        $dupService = app(CandidateDuplicateService::class);
        $merged = $dupService->mergeCandidates($master, $duplicate, 'Unit test merge verification');

        $this->assertEquals('FinTech Global', $merged->current_employer);
        $this->assertEquals(1, $merged->applications()->count());
        $this->assertSoftDeleted('candidates', ['id' => $duplicate->id]);
    }

    public function test_performance_benchmark_for_candidate_search_and_executive_dashboard(): void
    {
        // Insert 250 candidates in batch to benchmark indexed search & executive dashboard calculation speed
        $rows = [];
        $now = now();
        for ($i = 1; $i <= 250; $i++) {
            $rows[] = [
                'candidate_code' => sprintf('CND-BMK-%04d', $i),
                'full_name' => "Benchmark Candidate {$i}",
                'mobile_number' => sprintf('+91 97000%05d', $i),
                'normalized_mobile' => sprintf('97000%05d', $i),
                'email' => "bench{$i}@orbitouch-test.com",
                'normalized_email' => "bench{$i}@orbitouch-test.com",
                'current_location' => $i % 2 === 0 ? 'Mumbai' : 'Bengaluru',
                'total_experience' => 5.0,
                'relevant_experience' => 4.0,
                'notice_period_days' => 30,
                'source' => 'Direct Sourcing',
                'current_stage' => 'sourced',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        Candidate::insert($rows);

        $startTime = microtime(true);
        $analytics = app(DashboardAnalyticsService::class)->getExecutiveAnalytics();
        $searchResults = Candidate::where('normalized_mobile', '9700000125')->first();
        $elapsedMs = (microtime(true) - $startTime) * 1000;

        $this->assertNotNull($searchResults);
        $this->assertGreaterThanOrEqual(250, $analytics['overview']['total_candidates']);
        // Verify query and dashboard aggregation completes well under 1000ms target
        $this->assertLessThan(1000, $elapsedMs, "Dashboard + indexed search took {$elapsedMs}ms, exceeding 1000ms target.");
    }
}
