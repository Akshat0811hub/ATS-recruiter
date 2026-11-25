<?php

namespace App\Http\Controllers;

use App\Models\CallLog;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\ClientSubmission;
use App\Models\InterviewRecord;
use App\Models\JobAssignment;
use App\Models\JobRequirement;
use App\Models\JoiningRecord;
use App\Models\Offer;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
use App\Services\RecruitmentAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardAnalyticsService $analyticsService) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        if ($user->isClient()) {
            return redirect()->route('portal.dashboard');
        }

        $filters = $request->validate([
            'period' => ['nullable', 'in:all,today,yesterday,this_week,this_month,custom'],
            'from_date' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'recruiter_id' => ['nullable', 'integer'], 'team_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer'], 'job_id' => ['nullable', 'integer'],
        ]);
        if ($user->isRecruiter()) {
            $filters['recruiter_id'] = $user->id;
        }

        $analytics = $this->analyticsService->getExecutiveAnalytics($filters);
        $clients = RecruitmentAccess::query(Client::class)->orderBy('company_name')->get(['id', 'company_name', 'client_code']);
        $teams = RecruitmentAccess::query(Team::class)->orderBy('name')->get(['id', 'name', 'code']);
        $recruiters = RecruitmentAccess::query(User::class)->whereHas('role', fn ($q) => $q->whereIn('slug', ['recruiter', 'team_leader']))->orderBy('name')->get(['id', 'name']);
        $jobs = RecruitmentAccess::query(JobRequirement::class)->orderByDesc('id')->limit(50)->get(['id', 'job_code', 'title']);

        $todaysInterviews = RecruitmentAccess::query(InterviewRecord::class)->with(['candidate', 'jobRequirement', 'client'])
            ->whereDate('interview_date', Carbon::today()->toDateString())
            ->orderBy('interview_time')
            ->limit(10)
            ->get();

        $pendingTasks = RecruitmentAccess::query(Task::class)->with(['candidate', 'jobRequirement', 'assignee'])
            ->whereIn('status', ['pending', 'in_progress'])
            ->when($user->isRecruiter(), fn ($q) => $q->where('assigned_to', $user->id))
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        return view('dashboard.index', compact(
            'analytics',
            'filters',
            'clients',
            'teams',
            'recruiters',
            'jobs',
            'todaysInterviews',
            'pendingTasks'
        ));
    }

    public function myWork()
    {
        $user = Auth::user();
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();

        $assignedJobs = JobAssignment::with(['jobRequirement.client'])
            ->where('recruiter_id', $user->id)
            ->where('status', 'active')
            ->orderByDesc('is_primary')
            ->get();

        $sourcedToday = RecruitmentAccess::query(Candidate::class)->where('assigned_recruiter_id', $user->id)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $callsToday = RecruitmentAccess::query(CallLog::class)->where('recruiter_id', $user->id)
            ->whereBetween('called_at', [$todayStart, $todayEnd])
            ->count();

        $submittedToday = RecruitmentAccess::query(ClientSubmission::class)->where('recruiter_id', $user->id)
            ->whereBetween('submitted_at', [$todayStart, $todayEnd])
            ->count();

        $pendingClientFeedback = RecruitmentAccess::query(ClientSubmission::class)->with(['candidate', 'jobRequirement', 'client'])
            ->where('recruiter_id', $user->id)
            ->where('client_decision', 'pending_review')
            ->latest('submitted_at')
            ->limit(10)
            ->get();

        $todaysInterviews = RecruitmentAccess::query(InterviewRecord::class)->with(['candidate', 'jobRequirement', 'client'])
            ->whereDate('interview_date', Carbon::today()->toDateString())
            ->whereHas('candidateApplication', fn ($q) => $q->where('recruiter_id', $user->id))
            ->orderBy('interview_time')
            ->get();

        $todaysFollowUps = RecruitmentAccess::query(Task::class)->with(['candidate', 'jobRequirement'])
            ->where('assigned_to', $user->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereDate('due_date', Carbon::today()->toDateString())
            ->get();

        $overdueTasks = RecruitmentAccess::query(Task::class)->with(['candidate', 'jobRequirement'])
            ->where('assigned_to', $user->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereDate('due_date', '<', Carbon::today()->toDateString())
            ->get();

        $offerFollowUps = RecruitmentAccess::query(Offer::class)->with(['candidate', 'jobRequirement', 'client'])
            ->where('recruiter_id', $user->id)
            ->whereIn('status', ['offer_released', 'under_negotiation'])
            ->get();

        $upcomingJoinings = RecruitmentAccess::query(JoiningRecord::class)->with(['candidate', 'jobRequirement', 'client'])
            ->where('recruiter_id', $user->id)
            ->where('status', 'joining_pending')
            ->orderBy('expected_joining_date')
            ->get();

        $recentCandidates = RecruitmentAccess::query(Candidate::class)->where('assigned_recruiter_id', $user->id)
            ->latest()
            ->limit(15)
            ->get();

        return view('dashboard.my-work', compact(
            'user',
            'assignedJobs',
            'sourcedToday',
            'callsToday',
            'submittedToday',
            'pendingClientFeedback',
            'todaysInterviews',
            'todaysFollowUps',
            'overdueTasks',
            'offerFollowUps',
            'upcomingJoinings',
            'recentCandidates'
        ));
    }

    public function recruiterPerformance()
    {
        $analytics = $this->analyticsService->getExecutiveAnalytics();

        return view('dashboard.recruiter-performance', [
            'recruiters' => $analytics['recruiter_performance'],
            'teams' => $analytics['team_performance'],
        ]);
    }

    public function clientPerformance()
    {
        $analytics = $this->analyticsService->getExecutiveAnalytics();

        return view('dashboard.client-performance', [
            'clients' => $analytics['client_performance'],
        ]);
    }

    public function calendar(Request $request)
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = $request->query('date', today()->toDateString());
        $interviews = RecruitmentAccess::query(InterviewRecord::class)->with(['candidate', 'jobRequirement', 'client'])->whereDate('interview_date', $date)->orderBy('interview_time')->get();
        $tasks = RecruitmentAccess::query(Task::class)->with(['candidate', 'jobRequirement', 'assignee'])->whereDate('due_date', $date)->whereIn('status', ['pending', 'in_progress'])->orderBy('due_time')->get();
        $joinings = RecruitmentAccess::query(JoiningRecord::class)->with(['candidate', 'jobRequirement', 'client'])->whereDate('expected_joining_date', $date)->where('status', 'joining_pending')->get();

        return view('dashboard.calendar', compact('date', 'interviews', 'tasks', 'joinings'));
    }

    public function globalSearch(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $user = Auth::user();
        if ($user->isClient()) {
            $clientResults = collect();

            $jobs = RecruitmentAccess::query(JobRequirement::class)->where('client_id', $user->client_id)
                ->where(fn ($sub) => $sub->where('title', 'like', "%{$q}%")->orWhere('job_code', 'like', "%{$q}%"))
                ->limit(6)
                ->get();

            foreach ($jobs as $j) {
                $clientResults->push([
                    'type' => 'Job Requirement',
                    'code' => $j->job_code,
                    'title' => $j->title,
                    'subtitle' => $j->location,
                    'url' => route('portal.dashboard', ['job_id' => $j->id]),
                ]);
            }

            $submissions = RecruitmentAccess::query(ClientSubmission::class)
                ->with('jobRequirement')
                ->where('client_id', $user->client_id)
                ->where(fn ($sub) => $sub->where('snapshot_candidate_name', 'like', "%{$q}%")->orWhere('submission_code', 'like', "%{$q}%"))
                ->limit(6)
                ->get();

            foreach ($submissions as $sub) {
                $clientResults->push([
                    'type' => 'Submitted Profile',
                    'code' => $sub->submission_code,
                    'title' => $sub->snapshot_candidate_name,
                    'subtitle' => $sub->jobRequirement?->title ?? 'Submitted Candidate',
                    'url' => route('portal.dashboard', ['job_id' => $sub->job_requirement_id]),
                ]);
            }

            return response()->json(['results' => $clientResults->values()]);
        }

        $results = collect();

        $normPhone = Candidate::normalizePhone($q);
        $candidates = RecruitmentAccess::query(Candidate::class)
            ->where(function ($sub) use ($q, $normPhone) {
                $sub->where('full_name', 'like', "%{$q}%")
                    ->orWhere('candidate_code', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('mobile_number', 'like', "%{$q}%")
                    ->orWhere('current_employer', 'like', "%{$q}%")
                    ->orWhereHas('skills', fn ($sq) => $sq->where('skill_name', 'like', "%{$q}%"));
                if ($normPhone && strlen($normPhone) >= 4) {
                    $sub->orWhere('normalized_mobile', 'like', "%{$normPhone}%");
                }
            })
            ->limit(6)
            ->get();

        foreach ($candidates as $cand) {
            $results->push([
                'type' => 'Candidate',
                'code' => $cand->candidate_code,
                'title' => $cand->full_name,
                'subtitle' => "{$cand->current_designation} • {$cand->mobile_number}",
                'url' => route('candidates.show', $cand),
            ]);
        }

        $jobs = RecruitmentAccess::query(JobRequirement::class)->with('client')
            ->where(fn ($sub) => $sub->where('title', 'like', "%{$q}%")->orWhere('job_code', 'like', "%{$q}%"))
            ->limit(5)
            ->get();

        foreach ($jobs as $job) {
            $results->push([
                'type' => 'Job Requirement',
                'code' => $job->job_code,
                'title' => $job->title,
                'subtitle' => ($job->client?->company_name ?? '')." • {$job->remaining_count} open",
                'url' => route('jobs.show', $job),
            ]);
        }

        $clients = RecruitmentAccess::query(Client::class)->where(fn ($sub) => $sub->where('company_name', 'like', "%{$q}%")->orWhere('client_code', 'like', "%{$q}%"))
            ->limit(4)
            ->get();

        foreach ($clients as $client) {
            $results->push([
                'type' => 'Client',
                'code' => $client->client_code,
                'title' => $client->company_name,
                'subtitle' => "{$client->industry} • {$client->city}",
                'url' => route('clients.show', $client),
            ]);
        }

        $interviews = RecruitmentAccess::query(InterviewRecord::class)->with(['candidate', 'jobRequirement'])
            ->where(fn ($search) => $search->where('interview_code', 'like', "%{$q}%")->orWhere('interviewer_name', 'like', "%{$q}%"))
            ->limit(3)
            ->get();

        foreach ($interviews as $int) {
            $results->push([
                'type' => 'Interview',
                'code' => $int->interview_code,
                'title' => "{$int->round_name} — {$int->candidate?->full_name}",
                'subtitle' => $int->jobRequirement?->title ?? '',
                'url' => route('interviews.index', ['job_id' => $int->job_requirement_id]),
            ]);
        }

        return response()->json(['results' => $results->values()]);
    }
}
