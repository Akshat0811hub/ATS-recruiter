<?php

namespace App\Http\Controllers;

use App\Models\AtsNotification;
use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\Client;
use App\Models\ClientSubmission;
use App\Models\InterviewRecord;
use App\Models\JobRequirement;
use App\Models\Offer;
use App\Models\Placement;
use App\Models\ReplacementRecord;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DashboardAnalyticsService;
use App\Services\ExportImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ProductivityAndReportController extends Controller
{
    public const REPORT_TYPES = [
        'daily_recruiter_activity' => 'Daily Recruiter Activity Report',
        'weekly_recruiter_performance' => 'Weekly Recruiter Performance Report',
        'monthly_placements' => 'Monthly Placements & Revenue Report',
        'client_wise_recruitment' => 'Client-wise Recruitment Report',
        'job_wise_recruitment' => 'Job-wise Recruitment & Vacancy Report',
        'candidate_pipeline' => 'Candidate Pipeline Stage Report',
        'profile_submissions' => 'Profile Submission & Shortlist Report',
        'interviews' => 'Interview Schedule & Outcomes Report',
        'interview_round_results' => 'Multi-Round Interview Progression Report',
        'rejection_analysis' => 'Candidate Rejection Analysis Report',
        'offer_acceptance' => 'Offer Release & Acceptance Report',
        'candidate_dropout' => 'Candidate Dropout & Non-Joining Report',
        'joinings' => 'Confirmed Joinings & Warranty Report',
        'pending_vacancies' => 'Pending & Overdue Vacancies Report',
        'turnaround_time' => 'Recruitment Turnaround Time (TAT) Report',
        'source_effectiveness' => 'Recruitment Source Effectiveness Report',
        'followup_completion' => 'Task & Follow-up Completion Report',
        'replacement_obligations' => 'Replacement Obligations & Warranty Report',
    ];

    public function __construct(
        private readonly DashboardAnalyticsService $analyticsService,
        private readonly ExportImportService $exportImportService
    ) {}

    public function tasks(Request $request)
    {
        $user = Auth::user();
        $filter = $request->query('filter', 'today');

        $query = Task::with(['assignee', 'candidate', 'client', 'jobRequirement']);
        if ($user->isRecruiter()) {
            $query->where('assigned_to', $user->id);
        } elseif ($assigneeId = $request->query('assigned_to')) {
            $query->where('assigned_to', $assigneeId);
        }

        $todayStr = Carbon::today()->toDateString();
        if ($filter === 'today') {
            $query->whereDate('due_date', $todayStr)->whereIn('status', ['pending', 'in_progress']);
        } elseif ($filter === 'upcoming') {
            $query->whereDate('due_date', '>', $todayStr)->whereIn('status', ['pending', 'in_progress']);
        } elseif ($filter === 'overdue') {
            $query->whereDate('due_date', '<', $todayStr)->whereIn('status', ['pending', 'in_progress']);
        } elseif ($filter === 'completed') {
            $query->where('status', 'completed');
        }

        $tasks = $query->orderBy('due_date')->orderBy('due_time')->paginate(25)->withQueryString();
        $users = User::whereHas('role', fn ($q) => $q->whereNotIn('slug', ['client']))->where('is_active', true)->get();
        $candidates = Candidate::orderByDesc('id')->limit(100)->get(['id', 'candidate_code', 'full_name']);
        $jobs = JobRequirement::orderByDesc('id')->limit(100)->get(['id', 'job_code', 'title']);
        $clients = Client::orderBy('company_name')->get(['id', 'company_name']);

        return view('productivity.tasks', compact('tasks', 'filter', 'users', 'candidates', 'jobs', 'clients'));
    }

    public function storeTask(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'task_type' => ['required', 'in:'.implode(',', array_keys(Task::TYPES))],
            'assigned_to' => ['required', 'exists:users,id'],
            'candidate_id' => ['nullable', 'exists:candidates,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'job_requirement_id' => ['nullable', 'exists:job_requirements,id'],
            'priority' => ['required', 'in:critical,high,medium,low'],
            'due_date' => ['required', 'date'],
            'due_time' => ['nullable', 'string', 'max:20'],
            'is_recurring' => ['nullable', 'boolean'],
            'recurrence_pattern' => ['nullable', 'string', 'max:40'],
        ]);

        $task = Task::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'task_type' => $data['task_type'],
            'assigned_to' => $data['assigned_to'],
            'created_by' => Auth::id(),
            'candidate_id' => $data['candidate_id'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'job_requirement_id' => $data['job_requirement_id'] ?? null,
            'priority' => $data['priority'],
            'due_date' => $data['due_date'],
            'due_time' => $data['due_time'] ?? '11:00',
            'status' => 'pending',
            'is_recurring' => $request->boolean('is_recurring'),
            'recurrence_pattern' => $data['recurrence_pattern'] ?? null,
        ]);

        if ((int) $task->assigned_to !== (int) Auth::id()) {
            AtsNotification::sendToUser(
                $task->assigned_to,
                "New Task Assigned: {$task->title}",
                "Due on {$task->due_date->format('d M Y')} ({$task->priority} priority).",
                'task_assigned',
                '/tasks'
            );
        }

        AuditService::log('task.created', $task, $task->id);

        return back()->with('success', "Task [{$task->title}] created.");
    }

    public function completeTask(Request $request, Task $task)
    {
        $task->update([
            'status' => 'completed',
            'completed_at' => now(),
            'remarks' => $request->input('remarks', $task->remarks),
        ]);

        if ($task->is_recurring) {
            $nextDue = match ($task->recurrence_pattern) {
                'daily' => now()->addDay(),
                'monthly' => now()->addMonth(),
                default => now()->addWeek(),
            };
            Task::create([
                'title' => $task->title,
                'description' => $task->description,
                'task_type' => $task->task_type,
                'assigned_to' => $task->assigned_to,
                'created_by' => $task->created_by,
                'candidate_id' => $task->candidate_id,
                'client_id' => $task->client_id,
                'job_requirement_id' => $task->job_requirement_id,
                'priority' => $task->priority,
                'due_date' => $nextDue->toDateString(),
                'due_time' => $task->due_time,
                'status' => 'pending',
                'is_recurring' => true,
                'recurrence_pattern' => $task->recurrence_pattern,
            ]);
        }

        return back()->with('success', "Task [{$task->title}] marked as completed.");
    }

    public function notifications()
    {
        $user = Auth::user();
        $notifications = AtsNotification::where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('productivity.notifications', compact('notifications'));
    }

    public function markNotificationRead(AtsNotification $notification)
    {
        if ((int) $notification->notifiable_id === (int) Auth::id()) {
            $notification->update(['read_at' => now()]);
        }

        if ($notification->action_url) {
            return redirect($notification->action_url);
        }

        return back();
    }

    public function markAllNotificationsRead()
    {
        AtsNotification::where('notifiable_type', User::class)
            ->where('notifiable_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function reports(Request $request)
    {
        $reportType = $request->query('report_type', 'daily_recruiter_activity');
        if (! array_key_exists($reportType, self::REPORT_TYPES)) {
            $reportType = 'daily_recruiter_activity';
        }

        [$headers, $rows] = $this->buildReportDataset($reportType, $request->all());
        $reportTypes = self::REPORT_TYPES;
        $clients = Client::orderBy('company_name')->get();
        $recruiters = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['recruiter', 'team_leader']))->get();
        $jobs = JobRequirement::orderByDesc('id')->get();

        return view('productivity.reports', compact(
            'reportType',
            'reportTypes',
            'headers',
            'rows',
            'clients',
            'recruiters',
            'jobs'
        ));
    }

    public function exportReport(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasPermission('reports.export') && ! $user->hasRole(['super_admin', 'manager'])) {
            abort(403, 'Confidential report exports are restricted to Super Admin and Manager roles.');
        }

        $reportType = $request->query('report_type', 'daily_recruiter_activity');
        [$headers, $rows] = $this->buildReportDataset($reportType, $request->all());

        $filename = "orbitouch_report_{$reportType}_".now()->format('Ymd_His').'.csv';
        $csv = $this->exportImportService->generateSafeCsv("report_{$reportType}", $filename, $headers, $rows);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function buildReportDataset(string $reportType, array $filters): array
    {
        return match ($reportType) {
            'daily_recruiter_activity', 'weekly_recruiter_performance' => [
                ['Employee Code', 'Recruiter Name', 'Candidates Sourced', 'Calls Logged', 'Profiles Submitted', 'Active Applications', 'Placements Joined'],
                User::whereHas('role', fn ($q) => $q->whereIn('slug', ['recruiter', 'team_leader']))
                    ->withCount([
                        'sourcedCandidates',
                        'callLogs',
                        'clientSubmissions',
                        'candidateApplications as active_apps' => fn ($q) => $q->where('is_active', true),
                        'candidateApplications as joined_apps' => fn ($q) => $q->where('pipeline_stage', 'joined'),
                    ])
                    ->get()
                    ->map(fn ($u) => [
                        $u->employee_code,
                        $u->name,
                        $u->sourced_candidates_count,
                        $u->call_logs_count,
                        $u->client_submissions_count,
                        $u->active_apps,
                        $u->joined_apps,
                    ])->all(),
            ],
            'monthly_placements', 'joinings' => [
                ['Placement Code', 'Candidate Name', 'Client', 'Job Title', 'Joined Date', 'Final CTC', 'Billable Fee', 'Warranty Expiry', 'Status'],
                Placement::with(['candidate', 'client', 'jobRequirement'])->latest('joined_date')->limit(500)->get()->map(fn ($p) => [
                    $p->placement_code,
                    $p->candidate?->full_name,
                    $p->client?->company_name,
                    $p->jobRequirement?->title,
                    $p->joined_date?->toDateString(),
                    $p->final_ctc,
                    $p->billable_fee,
                    $p->warranty_end_date?->toDateString(),
                    $p->status,
                ])->all(),
            ],
            'client_wise_recruitment' => [
                ['Client Code', 'Company Name', 'Total Jobs', 'Submissions', 'Interviews', 'Offers', 'Confirmed Joinings'],
                Client::withCount(['jobRequirements', 'clientSubmissions', 'interviewRecords', 'offers', 'joiningRecords as joined_count' => fn ($q) => $q->whereIn('status', ['joined', 'placement_completed'])])->get()->map(fn ($c) => [
                    $c->client_code,
                    $c->company_name,
                    $c->job_requirements_count,
                    $c->client_submissions_count,
                    $c->interview_records_count,
                    $c->offers_count,
                    $c->joined_count,
                ])->all(),
            ],
            'job_wise_recruitment', 'pending_vacancies', 'turnaround_time' => [
                ['Job Code', 'Job Title', 'Client', 'Priority', 'Total Openings', 'Joined', 'Remaining', 'Status', 'Expected Closure'],
                JobRequirement::with('client')->orderByDesc('id')->limit(500)->get()->map(fn ($j) => [
                    $j->job_code,
                    $j->title,
                    $j->client?->company_name,
                    strtoupper($j->priority),
                    $j->openings_count,
                    $j->joined_count,
                    $j->remaining_count,
                    $j->status,
                    $j->expected_closure_date?->toDateString(),
                ])->all(),
            ],
            'profile_submissions', 'rejection_analysis' => [
                ['Submission Code', 'Candidate', 'Job Title', 'Client', 'Recruiter', 'Decision', 'Rejection Reason', 'Submitted At'],
                ClientSubmission::with(['jobRequirement', 'client', 'recruiter'])->orderByDesc('submitted_at')->limit(500)->get()->map(fn ($s) => [
                    $s->submission_code,
                    $s->snapshot_candidate_name,
                    $s->jobRequirement?->title,
                    $s->client?->company_name,
                    $s->recruiter?->name,
                    $s->client_decision,
                    $s->rejection_reason ?? '-',
                    $s->submitted_at?->format('Y-m-d H:i'),
                ])->all(),
            ],
            'interviews', 'interview_round_results' => [
                ['Interview Code', 'Candidate', 'Job Title', 'Client', 'Round #', 'Round Name', 'Interviewer', 'Date', 'Mode', 'Attempts', 'Status', 'Score'],
                InterviewRecord::with(['candidate', 'jobRequirement', 'client'])->orderByDesc('interview_date')->limit(500)->get()->map(fn ($i) => [
                    $i->interview_code,
                    $i->candidate?->full_name,
                    $i->jobRequirement?->title,
                    $i->client?->company_name,
                    $i->round_number,
                    $i->round_name,
                    $i->interviewer_name,
                    $i->interview_date?->toDateString(),
                    $i->mode,
                    $i->attempts_count,
                    $i->status,
                    $i->score ?? '-',
                ])->all(),
            ],
            'offer_acceptance' => [
                ['Offer Code', 'Candidate', 'Job Title', 'Client', 'Offered Salary', 'Negotiated Salary', 'Expected Joining', 'Status'],
                Offer::with(['candidate', 'jobRequirement', 'client'])->orderByDesc('id')->limit(500)->get()->map(fn ($o) => [
                    $o->offer_code,
                    $o->candidate?->full_name,
                    $o->jobRequirement?->title,
                    $o->client?->company_name,
                    $o->offered_salary,
                    $o->negotiated_salary,
                    $o->expected_joining_date?->toDateString(),
                    $o->status,
                ])->all(),
            ],
            'replacement_obligations' => [
                ['Client', 'Job Title', 'Exited Candidate', 'Exit Date', 'Exit Reason', 'Within Warranty', 'Vacancy Reopened', 'Status'],
                ReplacementRecord::with(['client', 'jobRequirement', 'originalCandidate'])->latest()->get()->map(fn ($r) => [
                    $r->client?->company_name,
                    $r->jobRequirement?->title,
                    $r->originalCandidate?->full_name,
                    $r->exit_date?->toDateString(),
                    $r->exit_reason,
                    $r->within_replacement_period ? 'Yes' : 'No',
                    $r->vacancy_reopened ? 'Yes' : 'No',
                    $r->status,
                ])->all(),
            ],
            default => [
                ['Application Code', 'Candidate', 'Job Title', 'Client', 'Recruiter', 'Pipeline Stage', 'Expected CTC', 'Dropout / Rejection Reason'],
                CandidateApplication::with(['candidate', 'jobRequirement', 'client', 'recruiter'])->orderByDesc('id')->limit(500)->get()->map(fn ($a) => [
                    $a->application_code,
                    $a->candidate?->full_name,
                    $a->jobRequirement?->title,
                    $a->client?->company_name,
                    $a->recruiter?->name,
                    $a->stageLabel(),
                    $a->expected_ctc,
                    $a->dropout_reason ?? $a->rejection_reason ?? '-',
                ])->all(),
            ],
        };
    }
}
