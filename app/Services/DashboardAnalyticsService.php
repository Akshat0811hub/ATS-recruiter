<?php

namespace App\Services;

use App\Models\CallLog;
use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\Client;
use App\Models\ClientSubmission;
use App\Models\InterviewRecord;
use App\Models\JobRequirement;
use App\Models\JoiningRecord;
use App\Models\Offer;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardAnalyticsService
{
    /**
     * Resolve date range filter (today, yesterday, this_week, this_month, custom, all).
     */
    public function resolveDateRange(?string $period, ?string $fromDate = null, ?string $toDate = null): array
    {
        $now = Carbon::now('Asia/Kolkata');

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'custom' => [
                $fromDate ? Carbon::parse($fromDate)->startOfDay() : $now->copy()->subDays(30)->startOfDay(),
                $toDate ? Carbon::parse($toDate)->endOfDay() : $now->copy()->endOfDay(),
            ],
            default => [null, null],
        };
    }

    /**
     * Executive Dashboard metrics with documented calculation formulas.
     * Avoids counting the same candidate application twice.
     */
    public function getExecutiveAnalytics(array $filters = []): array
    {
        [$startDate, $endDate] = $this->resolveDateRange(
            $filters['period'] ?? 'all',
            $filters['from_date'] ?? null,
            $filters['to_date'] ?? null
        );

        $recruiterId = ! empty($filters['recruiter_id']) ? (int) $filters['recruiter_id'] : null;
        $teamId = ! empty($filters['team_id']) ? (int) $filters['team_id'] : null;
        $clientId = ! empty($filters['client_id']) ? (int) $filters['client_id'] : null;
        $jobId = ! empty($filters['job_id']) ? (int) $filters['job_id'] : null;
        $location = ! empty($filters['location']) ? (string) $filters['location'] : null;

        $teamRecruiterIds = null;
        if ($teamId) {
            $teamRecruiterIds = User::where('team_id', $teamId)
                ->orWhereHas('teams', fn ($q) => $q->where('teams.id', $teamId))
                ->pluck('id')
                ->all();
        }

        // Job scope
        $jobsQuery = JobRequirement::query();
        if ($clientId) {
            $jobsQuery->where('client_id', $clientId);
        }
        if ($jobId) {
            $jobsQuery->where('id', $jobId);
        }
        if ($teamId) {
            $jobsQuery->where('assigned_team_id', $teamId);
        }
        if ($location) {
            $jobsQuery->where('location', 'like', "%{$location}%");
        }
        if ($recruiterId) {
            $jobsQuery->whereHas('assignments', fn ($q) => $q->where('recruiter_id', $recruiterId));
        }

        $activeJobStatuses = [
            'new_requirement', 'pending_review', 'approved', 'assigned',
            'sourcing_in_progress', 'interview_in_progress', 'offer_in_progress', 'partially_filled',
        ];

        // Application scope (distinct by candidate_applications.id to prevent double counting)
        $appsQuery = CandidateApplication::query();
        if ($clientId) {
            $appsQuery->where('client_id', $clientId);
        }
        if ($jobId) {
            $appsQuery->where('job_requirement_id', $jobId);
        }
        if ($recruiterId) {
            $appsQuery->where('recruiter_id', $recruiterId);
        } elseif ($teamRecruiterIds !== null) {
            $appsQuery->whereIn('recruiter_id', $teamRecruiterIds);
        }
        if ($startDate && $endDate) {
            $appsQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();

        // Core Overview Metrics
        $totalClients = Client::count();
        $activeClients = Client::where('status', 'active')->count();
        $activeRequirements = (clone $jobsQuery)->whereIn('status', $activeJobStatuses)->count();
        $openVacancies = (int) (clone $jobsQuery)->whereIn('status', $activeJobStatuses)->sum('remaining_count');
        $filledVacancies = (int) (clone $jobsQuery)->sum('joined_count');
        $overdueRequirements = (clone $jobsQuery)
            ->whereIn('status', $activeJobStatuses)
            ->whereNotNull('expected_closure_date')
            ->where('expected_closure_date', '<', Carbon::today()->toDateString())
            ->where('remaining_count', '>', 0)
            ->count();

        $totalCandidates = Candidate::count();
        $newCandidatesToday = Candidate::whereBetween('created_at', [$todayStart, $todayEnd])->count();
        $callsRecordedToday = CallLog::whereBetween('called_at', [$todayStart, $todayEnd])
            ->when($recruiterId, fn ($q) => $q->where('recruiter_id', $recruiterId))
            ->count();

        $profilesSubmittedToday = ClientSubmission::whereBetween('submitted_at', [$todayStart, $todayEnd])
            ->when($recruiterId, fn ($q) => $q->where('recruiter_id', $recruiterId))
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->count();

        $totalSubmissions = ClientSubmission::query()
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when($jobId, fn ($q) => $q->where('job_requirement_id', $jobId))
            ->when($recruiterId, fn ($q) => $q->where('recruiter_id', $recruiterId))
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('submitted_at', [$startDate, $endDate]))
            ->count();

        $clientShortlists = ClientSubmission::query()
            ->whereIn('client_decision', ['shortlisted', 'interview_requested'])
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when($jobId, fn ($q) => $q->where('job_requirement_id', $jobId))
            ->when($recruiterId, fn ($q) => $q->where('recruiter_id', $recruiterId))
            ->count();

        $pendingClientFeedback = ClientSubmission::query()
            ->where('client_decision', 'pending_review')
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when($jobId, fn ($q) => $q->where('job_requirement_id', $jobId))
            ->when($recruiterId, fn ($q) => $q->where('recruiter_id', $recruiterId))
            ->count();

        $interviewsScheduledToday = InterviewRecord::query()
            ->whereDate('interview_date', Carbon::today()->toDateString())
            ->whereIn('status', ['scheduled', 'rescheduled', 'completed', 'passed'])
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->count();

        $distinctInterviewedApps = InterviewRecord::query()
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when($jobId, fn ($q) => $q->where('job_requirement_id', $jobId))
            ->distinct('candidate_application_id')
            ->count('candidate_application_id');

        $candidatesSelected = (clone $appsQuery)
            ->whereIn('pipeline_stage', ['selected', 'offer_released', 'offer_accepted', 'joining_pending', 'joined'])
            ->count();

        $offersReleased = Offer::query()
            ->whereIn('status', ['offer_released', 'under_negotiation', 'offer_accepted', 'offer_rejected', 'candidate_backed_out'])
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when($jobId, fn ($q) => $q->where('job_requirement_id', $jobId))
            ->when($recruiterId, fn ($q) => $q->where('recruiter_id', $recruiterId))
            ->count();

        $offersAccepted = Offer::query()
            ->where('status', 'offer_accepted')
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when($jobId, fn ($q) => $q->where('job_requirement_id', $jobId))
            ->when($recruiterId, fn ($q) => $q->where('recruiter_id', $recruiterId))
            ->count();

        $upcomingJoinings = JoiningRecord::query()
            ->where('status', 'joining_pending')
            ->where('expected_joining_date', '>=', Carbon::today()->toDateString())
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->count();

        $candidatesJoined = JoiningRecord::query()
            ->whereIn('status', ['joined', 'placement_completed'])
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when($jobId, fn ($q) => $q->where('job_requirement_id', $jobId))
            ->when($recruiterId, fn ($q) => $q->where('recruiter_id', $recruiterId))
            ->count();

        $pendingFollowUps = Task::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->when($recruiterId, fn ($q) => $q->where('assigned_to', $recruiterId))
            ->count();

        // Conversion Ratios (Explicitly documented formulas)
        // 1. Submission-to-Shortlist = (Shortlisted Submissions / Total Submissions) * 100
        $submissionToShortlistRate = $totalSubmissions > 0
            ? round(($clientShortlists / $totalSubmissions) * 100, 1)
            : 0.0;

        // 2. Shortlist-to-Interview = (Distinct Interviewed Applications / Shortlisted Submissions) * 100
        $shortlistToInterviewRate = $clientShortlists > 0
            ? round((min($distinctInterviewedApps, $clientShortlists) / $clientShortlists) * 100, 1)
            : 0.0;

        // 3. Interview-to-Selection = (Selected Applications / Distinct Interviewed Applications) * 100
        $interviewToSelectionRate = $distinctInterviewedApps > 0
            ? round((min($candidatesSelected, $distinctInterviewedApps) / $distinctInterviewedApps) * 100, 1)
            : 0.0;

        // 4. Offer Acceptance Ratio = (Accepted Offers / Released Offers) * 100
        $offerAcceptanceRate = $offersReleased > 0
            ? round(($offersAccepted / $offersReleased) * 100, 1)
            : 0.0;

        // 5. Offer-to-Joining Conversion = (Confirmed Joinings / Accepted Offers) * 100
        $offerToJoiningRate = $offersAccepted > 0
            ? round((min($candidatesJoined, $offersAccepted) / $offersAccepted) * 100, 1)
            : 0.0;

        // Stage Funnel Counts (Distinct candidate applications per current stage)
        $stageBreakdown = (clone $appsQuery)
            ->select('pipeline_stage', DB::raw('COUNT(DISTINCT id) as total'))
            ->groupBy('pipeline_stage')
            ->pluck('total', 'pipeline_stage')
            ->all();

        // Source Effectiveness
        $sourceEffectiveness = Candidate::query()
            ->select('source', DB::raw('COUNT(*) as total_candidates'))
            ->groupBy('source')
            ->orderByDesc('total_candidates')
            ->limit(8)
            ->get();

        // Dropout / Non-joining Reasons
        $dropoutReasons = CandidateApplication::query()
            ->whereNotNull('dropout_reason')
            ->select('dropout_reason', DB::raw('COUNT(*) as total'))
            ->groupBy('dropout_reason')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        // Recruiter Performance Leaderboard
        $recruiterPerformance = User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['recruiter', 'team_leader']))
            ->withCount([
                'sourcedCandidates',
                'callLogs',
                'clientSubmissions',
                'candidateApplications as active_applications_count' => fn ($q) => $q->where('is_active', true),
                'candidateApplications as joined_applications_count' => fn ($q) => $q->where('pipeline_stage', 'joined'),
            ])
            ->orderByDesc('joined_applications_count')
            ->orderByDesc('client_submissions_count')
            ->limit(15)
            ->get();

        // Client Hiring Performance
        $clientPerformance = Client::query()
            ->withCount([
                'jobRequirements',
                'clientSubmissions',
                'interviewRecords',
                'offers',
                'joiningRecords as joined_count' => fn ($q) => $q->whereIn('status', ['joined', 'placement_completed']),
            ])
            ->orderByDesc('joined_count')
            ->limit(10)
            ->get();

        // Team Performance
        $teamPerformance = Team::query()
            ->with(['manager', 'teamLeader'])
            ->withCount(['directUsers as recruiters_count', 'jobRequirements'])
            ->get();

        return [
            'overview' => [
                'total_clients' => $totalClients,
                'active_clients' => $activeClients,
                'active_requirements' => $activeRequirements,
                'open_vacancies' => $openVacancies,
                'filled_vacancies' => $filledVacancies,
                'total_candidates' => $totalCandidates,
                'new_candidates_today' => $newCandidatesToday,
                'calls_recorded_today' => $callsRecordedToday,
                'profiles_submitted_today' => $profilesSubmittedToday,
                'total_submissions' => $totalSubmissions,
                'client_shortlists' => $clientShortlists,
                'interviews_scheduled_today' => $interviewsScheduledToday,
                'distinct_interviewed' => $distinctInterviewedApps,
                'candidates_selected' => $candidatesSelected,
                'offers_released' => $offersReleased,
                'offers_accepted' => $offersAccepted,
                'upcoming_joining_dates' => $upcomingJoinings,
                'candidates_joined' => $candidatesJoined,
                'pending_follow_ups' => $pendingFollowUps,
                'overdue_requirements' => $overdueRequirements,
                'pending_client_feedback' => $pendingClientFeedback,
            ],
            'conversions' => [
                'submission_to_shortlist' => $submissionToShortlistRate,
                'shortlist_to_interview' => $shortlistToInterviewRate,
                'interview_to_selection' => $interviewToSelectionRate,
                'offer_acceptance_ratio' => $offerAcceptanceRate,
                'offer_to_joining' => $offerToJoiningRate,
            ],
            'formulas' => [
                'submission_to_shortlist' => '(Client Shortlisted Submissions / Total Client Submissions) × 100',
                'shortlist_to_interview' => '(Distinct Interviewed Applications / Client Shortlisted Submissions) × 100',
                'interview_to_selection' => '(Selected Applications / Distinct Interviewed Applications) × 100',
                'offer_acceptance_ratio' => '(Accepted Offers / Total Offers Released) × 100',
                'offer_to_joining' => '(Confirmed Candidate Joinings / Accepted Offers) × 100',
            ],
            'funnel' => $stageBreakdown,
            'source_effectiveness' => $sourceEffectiveness,
            'dropout_reasons' => $dropoutReasons,
            'recruiter_performance' => $recruiterPerformance,
            'client_performance' => $clientPerformance,
            'team_performance' => $teamPerformance,
        ];
    }
}
