<?php

namespace App\Http\Controllers;

use App\Models\AtsNotification;
use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\Client;
use App\Models\ClientSubmission;
use App\Models\InterviewRecord;
use App\Models\JobRequirement;
use App\Models\JoiningRecord;
use App\Models\Offer;
use App\Models\Placement;
use App\Models\ReplacementRecord;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DashboardAnalyticsService;
use App\Services\ExportImportService;
use App\Services\RecruitmentAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        $query = RecruitmentAccess::query(Task::class)->with(['assignee', 'candidate', 'client', 'jobRequirement']);
        if ($user->isRecruiter()) {
            $query->where('assigned_to', $user->id);
        } elseif ($assigneeId = $request->query('assigned_to')) {
            $query->where('assigned_to', (int) $assigneeId);
        }

        $todayStr = Carbon::today()->toDateString();
        if ($filter === 'today') {
            $query->whereDate('due_date', $todayStr)->whereIn('status', ['pending', 'in_progress']);
        } elseif ($filter === 'pending') {
            $query->whereIn('status', ['pending', 'in_progress']);
        } elseif ($filter === 'upcoming') {
            $query->whereDate('due_date', '>', $todayStr)->whereIn('status', ['pending', 'in_progress']);
        } elseif ($filter === 'overdue') {
            $query->whereDate('due_date', '<', $todayStr)->whereIn('status', ['pending', 'in_progress']);
        } elseif ($filter === 'completed') {
            $query->where('status', 'completed');
        }

        $tasks = $query->orderBy('due_date')->orderBy('due_time')->paginate(25)->withQueryString();
        $users = RecruitmentAccess::query(User::class)->whereHas('role', fn ($q) => $q->whereNotIn('slug', ['client']))->where('is_active', true)->get();
        $candidates = RecruitmentAccess::query(Candidate::class)->orderByDesc('id')->limit(100)->get(['id', 'candidate_code', 'full_name']);
        $jobs = RecruitmentAccess::query(JobRequirement::class)->orderByDesc('id')->limit(100)->get(['id', 'job_code', 'title']);
        $clients = RecruitmentAccess::query(Client::class)->orderBy('company_name')->get(['id', 'company_name']);

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
            'recurrence_pattern' => ['nullable', 'in:daily,weekly,monthly'],
        ], [
            'title.required' => 'Enter a short title for the follow-up task.',
            'due_date.required' => 'Select a due date for the task.',
        ]);

        $isRecurring = $request->boolean('is_recurring');

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
            'is_recurring' => $isRecurring,
            'recurrence_pattern' => $isRecurring ? ($data['recurrence_pattern'] ?? 'weekly') : null,
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
        $alreadyCompleted = false;

        DB::transaction(function () use ($request, $task, &$alreadyCompleted) {
            $lockedTask = Task::lockForUpdate()->findOrFail($task->id);

            if ($lockedTask->status === 'completed') {
                $alreadyCompleted = true;
                if ($request->filled('remarks')) {
                    $lockedTask->update(['remarks' => $request->input('remarks')]);
                }

                return;
            }

            $lockedTask->update([
                'status' => 'completed',
                'completed_at' => now(),
                'remarks' => $request->input('remarks', $lockedTask->remarks),
            ]);

            if ($lockedTask->is_recurring) {
                $baseDate = $lockedTask->due_date && $lockedTask->due_date->isFuture()
                    ? $lockedTask->due_date->copy()
                    : Carbon::today();

                $nextDue = match ($lockedTask->recurrence_pattern) {
                    'daily' => $baseDate->addDay(),
                    'monthly' => $baseDate->addMonth(),
                    default => $baseDate->addWeek(),
                };

                $triggerKey = "recurring_parent_{$lockedTask->id}";

                $duplicateOpenExists = Task::where('auto_trigger_key', $triggerKey)
                    ->orWhere(function (Builder $q) use ($lockedTask, $nextDue) {
                        $q->where('assigned_to', $lockedTask->assigned_to)
                            ->where('title', $lockedTask->title)
                            ->where('task_type', $lockedTask->task_type)
                            ->where('is_recurring', true)
                            ->whereIn('status', ['pending', 'in_progress'])
                            ->whereDate('due_date', $nextDue->toDateString())
                            ->when($lockedTask->candidate_id, fn ($cq) => $cq->where('candidate_id', $lockedTask->candidate_id), fn ($cq) => $cq->whereNull('candidate_id'))
                            ->when($lockedTask->job_requirement_id, fn ($jq) => $jq->where('job_requirement_id', $lockedTask->job_requirement_id), fn ($jq) => $jq->whereNull('job_requirement_id'));
                    })
                    ->exists();

                if (! $duplicateOpenExists) {
                    Task::create([
                        'title' => $lockedTask->title,
                        'description' => $lockedTask->description,
                        'task_type' => $lockedTask->task_type,
                        'assigned_to' => $lockedTask->assigned_to,
                        'created_by' => $lockedTask->created_by,
                        'candidate_id' => $lockedTask->candidate_id,
                        'client_id' => $lockedTask->client_id,
                        'job_requirement_id' => $lockedTask->job_requirement_id,
                        'candidate_application_id' => $lockedTask->candidate_application_id,
                        'priority' => $lockedTask->priority,
                        'due_date' => $nextDue->toDateString(),
                        'due_time' => $lockedTask->due_time,
                        'status' => 'pending',
                        'is_recurring' => true,
                        'recurrence_pattern' => $lockedTask->recurrence_pattern ?? 'weekly',
                        'auto_trigger_key' => $triggerKey,
                    ]);
                }
            }
        });

        if ($alreadyCompleted) {
            return back()->with('status', "Task [{$task->title}] was already completed.");
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

    private function validateReportFilters(Request $request): array
    {
        $validated = $request->validate([
            'report_type' => ['nullable', 'string'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'recruiter_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer'],
            'job_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:60'],
        ], [
            'from_date.date_format' => 'Enter a valid From date (YYYY-MM-DD).',
            'to_date.date_format' => 'Enter a valid To date (YYYY-MM-DD).',
            'to_date.after_or_equal' => 'The To date must be on or after the From date.',
        ]);

        $reportType = $validated['report_type'] ?? 'daily_recruiter_activity';
        if (! array_key_exists($reportType, self::REPORT_TYPES)) {
            $reportType = 'daily_recruiter_activity';
        }
        $validated['report_type'] = $reportType;

        return $validated;
    }

    public function reports(Request $request)
    {
        $filters = $this->validateReportFilters($request);
        $reportType = $filters['report_type'];

        [$headers, $rows] = $this->buildReportDataset($reportType, $filters);
        $reportTypes = self::REPORT_TYPES;
        $clients = RecruitmentAccess::query(Client::class)->orderBy('company_name')->get();
        $recruiters = RecruitmentAccess::query(User::class)->whereHas('role', fn ($q) => $q->whereIn('slug', ['recruiter', 'team_leader']))->orderBy('name')->get();
        $jobs = RecruitmentAccess::query(JobRequirement::class)->orderByDesc('id')->get();

        return view('productivity.reports', compact(
            'reportType',
            'reportTypes',
            'filters',
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

        $filters = $this->validateReportFilters($request);
        $reportType = $filters['report_type'];
        [$headers, $rows] = $this->buildReportDataset($reportType, $filters);

        $filename = "orbitouch_report_{$reportType}_".now()->format('Ymd_His').'.csv';
        $csv = $this->exportImportService->generateSafeCsv("report_{$reportType}", $filename, $headers, $rows);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function buildReportDataset(string $reportType, array $filters): array
    {
        $fromDate = ! empty($filters['from_date']) ? Carbon::parse($filters['from_date'])->startOfDay() : null;
        $toDate = ! empty($filters['to_date']) ? Carbon::parse($filters['to_date'])->endOfDay() : null;
        $recruiterId = ! empty($filters['recruiter_id']) ? (int) $filters['recruiter_id'] : null;
        $clientId = ! empty($filters['client_id']) ? (int) $filters['client_id'] : null;
        $jobId = ! empty($filters['job_id']) ? (int) $filters['job_id'] : null;
        $status = ! empty($filters['status']) ? trim((string) $filters['status']) : null;

        if (Auth::user()?->isRecruiter()) {
            $recruiterId = (int) Auth::id();
        }

        $applyDate = static function ($query, string $column, bool $dateOnly = false) use ($fromDate, $toDate) {
            if ($fromDate && $toDate) {
                if ($dateOnly) {
                    $query->whereDate($column, '>=', $fromDate->toDateString())
                        ->whereDate($column, '<=', $toDate->toDateString());
                } else {
                    $query->whereBetween($column, [$fromDate, $toDate]);
                }
            } elseif ($fromDate) {
                $dateOnly
                    ? $query->whereDate($column, '>=', $fromDate->toDateString())
                    : $query->where($column, '>=', $fromDate);
            } elseif ($toDate) {
                $dateOnly
                    ? $query->whereDate($column, '<=', $toDate->toDateString())
                    : $query->where($column, '<=', $toDate);
            }
        };

        return match ($reportType) {
            'daily_recruiter_activity', 'weekly_recruiter_performance' => [
                ['Employee Code', 'Recruiter Name', 'Candidates Sourced', 'Calls Logged', 'Profiles Submitted', 'Active Applications', 'Placements Joined'],
                RecruitmentAccess::query(User::class)
                    ->whereHas('role', fn ($q) => $q->whereIn('slug', ['recruiter', 'team_leader']))
                    ->when($recruiterId, fn ($q) => $q->where('users.id', $recruiterId))
                    ->when($jobId, fn ($q) => $q->where(function ($sub) use ($jobId) {
                        $sub->whereHas('jobAssignments', fn ($ja) => $ja->where('job_requirement_id', $jobId))
                            ->orWhereHas('candidateApplications', fn ($ca) => $ca->where('job_requirement_id', $jobId));
                    }))
                    ->when($clientId, fn ($q) => $q->where(function ($sub) use ($clientId) {
                        $sub->whereHas('jobAssignments.jobRequirement', fn ($jr) => $jr->where('client_id', $clientId))
                            ->orWhereHas('candidateApplications', fn ($ca) => $ca->where('client_id', $clientId));
                    }))
                    ->withCount([
                        'sourcedCandidates' => function ($q) use ($applyDate, $clientId, $jobId) {
                            $applyDate($q, 'candidates.created_at');
                            if ($clientId || $jobId) {
                                $q->whereHas('applications', function ($aq) use ($clientId, $jobId) {
                                    $aq->when($clientId, fn ($sq) => $sq->where('client_id', $clientId))
                                        ->when($jobId, fn ($sq) => $sq->where('job_requirement_id', $jobId));
                                });
                            }
                        },
                        'callLogs' => function ($q) use ($applyDate, $clientId, $jobId) {
                            $applyDate($q, 'call_logs.called_at');
                            $q->when($jobId, fn ($sq) => $sq->where('job_requirement_id', $jobId))
                                ->when($clientId, fn ($sq) => $sq->whereHas('jobRequirement', fn ($jr) => $jr->where('client_id', $clientId)));
                        },
                        'clientSubmissions' => function ($q) use ($applyDate, $clientId, $jobId, $status) {
                            $applyDate($q, 'client_submissions.submitted_at');
                            $q->when($clientId, fn ($sq) => $sq->where('client_id', $clientId))
                                ->when($jobId, fn ($sq) => $sq->where('job_requirement_id', $jobId))
                                ->when($status, fn ($sq) => $sq->where('client_decision', $status));
                        },
                        'candidateApplications as active_apps' => function ($q) use ($applyDate, $clientId, $jobId, $status) {
                            $q->where('is_active', true)
                                ->when($clientId, fn ($sq) => $sq->where('client_id', $clientId))
                                ->when($jobId, fn ($sq) => $sq->where('job_requirement_id', $jobId))
                                ->when($status, fn ($sq) => $sq->where('pipeline_stage', $status));
                            $applyDate($q, 'candidate_applications.created_at');
                        },
                        'candidateApplications as joined_apps' => function ($q) use ($applyDate, $clientId, $jobId) {
                            $q->where('pipeline_stage', 'joined')
                                ->when($clientId, fn ($sq) => $sq->where('client_id', $clientId))
                                ->when($jobId, fn ($sq) => $sq->where('job_requirement_id', $jobId));
                            $applyDate($q, 'candidate_applications.stage_updated_at');
                        },
                    ])
                    ->orderBy('name')
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
            'monthly_placements' => [
                ['Placement Code', 'Candidate Name', 'Client', 'Job Title', 'Joined Date', 'Final CTC', 'Billable Fee', 'Warranty Expiry', 'Status'],
                RecruitmentAccess::query(Placement::class)
                    ->with(['candidate', 'client', 'jobRequirement'])
                    ->when($recruiterId, fn ($q) => $q->where('recruiter_id', $recruiterId))
                    ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
                    ->when($jobId, fn ($q) => $q->where('job_requirement_id', $jobId))
                    ->when($status, fn ($q) => $q->where('status', $status))
                    ->tap(fn ($q) => $applyDate($q, 'joined_date', true))
                    ->latest('joined_date')
                    ->limit(500)
                    ->get()
                    ->map(fn ($p) => [
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
            'joinings' => [
                ['Joining Code', 'Candidate Name', 'Client', 'Job Title', 'Expected Joining', 'Actual Joining', 'Final Salary', 'Warranty Expiry', 'Status'],
                RecruitmentAccess::query(JoiningRecord::class)
                    ->with(['candidate', 'client', 'jobRequirement'])
                    ->when($recruiterId, fn ($q) => $q->where('recruiter_id', $recruiterId))
                    ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
                    ->when($jobId, fn ($q) => $q->where('job_requirement_id', $jobId))
                    ->when($status, fn ($q) => $q->where('status', $status))
                    ->tap(fn ($q) => $applyDate($q, 'expected_joining_date', true))
                    ->orderByDesc('expected_joining_date')
                    ->limit(500)
                    ->get()
                    ->map(fn ($j) => [
                        $j->joining_code,
                        $j->candidate?->full_name,
                        $j->client?->company_name,
                        $j->jobRequirement?->title,
                        $j->expected_joining_date?->toDateString(),
                        $j->actual_joining_date?->toDateString() ?? '-',
                        $j->final_salary,
                        $j->replacement_expiry_date?->toDateString() ?? '-',
                        $j->status,
                    ])->all(),
            ],
            'client_wise_recruitment' => [
                ['Client Code', 'Company Name', 'Total Jobs', 'Submissions', 'Interviews', 'Offers', 'Confirmed Joinings'],
                RecruitmentAccess::query(Client::class)
                    ->when($clientId, fn ($q) => $q->where('clients.id', $clientId))
                    ->when($status, fn ($q) => $q->where('clients.status', $status))
                    ->when($jobId, fn ($q) => $q->whereHas('jobRequirements', fn ($jq) => $jq->where('job_requirements.id', $jobId)))
                    ->when($recruiterId, fn ($q) => $q->whereHas('jobRequirements.assignments', fn ($aq) => $aq->where('recruiter_id', $recruiterId)))
                    ->withCount([
                        'jobRequirements' => function ($q) use ($applyDate, $jobId, $recruiterId) {
                            $q->when($jobId, fn ($sq) => $sq->where('id', $jobId))
                                ->when($recruiterId, fn ($sq) => $sq->whereHas('assignments', fn ($aq) => $aq->where('recruiter_id', $recruiterId)));
                            $applyDate($q, 'job_requirements.created_at');
                        },
                        'clientSubmissions' => function ($q) use ($applyDate, $jobId, $recruiterId) {
                            $q->when($jobId, fn ($sq) => $sq->where('job_requirement_id', $jobId))
                                ->when($recruiterId, fn ($sq) => $sq->where('recruiter_id', $recruiterId));
                            $applyDate($q, 'client_submissions.submitted_at');
                        },
                        'interviewRecords' => function ($q) use ($applyDate, $jobId, $recruiterId) {
                            $q->when($jobId, fn ($sq) => $sq->where('job_requirement_id', $jobId))
                                ->when($recruiterId, fn ($sq) => $sq->whereHas('candidateApplication', fn ($aq) => $aq->where('recruiter_id', $recruiterId)));
                            $applyDate($q, 'interview_records.interview_date', true);
                        },
                        'offers' => function ($q) use ($applyDate, $jobId, $recruiterId) {
                            $q->when($jobId, fn ($sq) => $sq->where('job_requirement_id', $jobId))
                                ->when($recruiterId, fn ($sq) => $sq->where('recruiter_id', $recruiterId));
                            $applyDate($q, 'offers.selection_date', true);
                        },
                        'joiningRecords as joined_count' => function ($q) use ($applyDate, $jobId, $recruiterId) {
                            $q->whereIn('status', ['joined', 'placement_completed'])
                                ->when($jobId, fn ($sq) => $sq->where('job_requirement_id', $jobId))
                                ->when($recruiterId, fn ($sq) => $sq->where('recruiter_id', $recruiterId));
                            $applyDate($q, 'joining_records.actual_joining_date', true);
                        },
                    ])
                    ->orderBy('company_name')
                    ->get()
                    ->map(fn ($c) => [
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
                RecruitmentAccess::query(JobRequirement::class)
                    ->with('client')
                    ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
                    ->when($jobId, fn ($q) => $q->where('job_requirements.id', $jobId))
                    ->when($status, fn ($q) => $q->where('status', $status))
                    ->when($recruiterId, fn ($q) => $q->whereHas('assignments', fn ($aq) => $aq->where('recruiter_id', $recruiterId)))
                    ->when($reportType === 'pending_vacancies', fn ($q) => $q->where('remaining_count', '>', 0)->whereNotIn('status', ['successfully_closed', 'cancelled']))
                    ->tap(fn ($q) => $applyDate($q, 'job_requirements.created_at'))
                    ->orderByDesc('id')
                    ->limit(500)
                    ->get()
                    ->map(fn ($j) => [
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
                RecruitmentAccess::query(ClientSubmission::class)
                    ->with(['jobRequirement', 'client', 'recruiter'])
                    ->when($recruiterId, fn ($q) => $q->where('recruiter_id', $recruiterId))
                    ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
                    ->when($jobId, fn ($q) => $q->where('job_requirement_id', $jobId))
                    ->when($status, fn ($q) => $q->where('client_decision', $status))
                    ->when($reportType === 'rejection_analysis' && ! $status, fn ($q) => $q->where('client_decision', 'rejected'))
                    ->tap(fn ($q) => $applyDate($q, 'submitted_at'))
                    ->orderByDesc('submitted_at')
                    ->limit(500)
                    ->get()
                    ->map(fn ($s) => [
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
                RecruitmentAccess::query(InterviewRecord::class)
                    ->with(['candidate', 'jobRequirement', 'client'])
                    ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
                    ->when($jobId, fn ($q) => $q->where('job_requirement_id', $jobId))
                    ->when($status, fn ($q) => $q->where('status', $status))
                    ->when($recruiterId, fn ($q) => $q->whereHas('candidateApplication', fn ($aq) => $aq->where('recruiter_id', $recruiterId)))
                    ->tap(fn ($q) => $applyDate($q, 'interview_date', true))
                    ->orderByDesc('interview_date')
                    ->limit(500)
                    ->get()
                    ->map(fn ($i) => [
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
                RecruitmentAccess::query(Offer::class)
                    ->with(['candidate', 'jobRequirement', 'client'])
                    ->when($recruiterId, fn ($q) => $q->where('recruiter_id', $recruiterId))
                    ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
                    ->when($jobId, fn ($q) => $q->where('job_requirement_id', $jobId))
                    ->when($status, fn ($q) => $q->where('status', $status))
                    ->tap(fn ($q) => $applyDate($q, 'selection_date', true))
                    ->orderByDesc('id')
                    ->limit(500)
                    ->get()
                    ->map(fn ($o) => [
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
            'source_effectiveness' => [
                ['Candidate Source', 'Total Candidates', 'Submitted to Clients', 'Confirmed Joined'],
                RecruitmentAccess::query(Candidate::class)
                    ->when($recruiterId, fn ($q) => $q->where('assigned_recruiter_id', $recruiterId))
                    ->when($status, fn ($q) => $q->where('current_stage', $status))
                    ->when($clientId || $jobId, fn ($q) => $q->whereHas('applications', function ($aq) use ($clientId, $jobId) {
                        $aq->when($clientId, fn ($sq) => $sq->where('client_id', $clientId))
                            ->when($jobId, fn ($sq) => $sq->where('job_requirement_id', $jobId));
                    }))
                    ->tap(fn ($q) => $applyDate($q, 'candidates.created_at'))
                    ->select(
                        'source',
                        DB::raw('COUNT(*) as total_candidates'),
                        DB::raw("SUM(CASE WHEN current_stage IN ('submitted','client_shortlisted','interview_scheduled','interview_in_progress','selected','offer_released','offer_accepted','joining_pending','joined') THEN 1 ELSE 0 END) as submitted_count"),
                        DB::raw("SUM(CASE WHEN current_stage = 'joined' THEN 1 ELSE 0 END) as joined_count")
                    )
                    ->groupBy('source')
                    ->orderByDesc('total_candidates')
                    ->get()
                    ->map(fn ($row) => [
                        $row->source ?: 'Unspecified',
                        (int) $row->total_candidates,
                        (int) $row->submitted_count,
                        (int) $row->joined_count,
                    ])->all(),
            ],
            'followup_completion' => [
                ['Task Title', 'Type', 'Assigned User', 'Candidate', 'Job Code', 'Priority', 'Due Date', 'Status', 'Completed At'],
                RecruitmentAccess::query(Task::class)
                    ->with(['assignee', 'candidate', 'jobRequirement'])
                    ->when($recruiterId, fn ($q) => $q->where('assigned_to', $recruiterId))
                    ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
                    ->when($jobId, fn ($q) => $q->where('job_requirement_id', $jobId))
                    ->when($status, fn ($q) => $q->where('status', $status))
                    ->tap(fn ($q) => $applyDate($q, 'due_date', true))
                    ->orderByDesc('due_date')
                    ->limit(500)
                    ->get()
                    ->map(fn ($t) => [
                        $t->title,
                        Task::TYPES[$t->task_type] ?? $t->task_type,
                        $t->assignee?->name,
                        $t->candidate?->full_name ?? '-',
                        $t->jobRequirement?->job_code ?? '-',
                        strtoupper($t->priority),
                        $t->due_date?->toDateString(),
                        $t->status,
                        $t->completed_at?->format('Y-m-d H:i') ?? '-',
                    ])->all(),
            ],
            'replacement_obligations' => [
                ['Client', 'Job Title', 'Exited Candidate', 'Exit Date', 'Exit Reason', 'Within Warranty', 'Vacancy Reopened', 'Status'],
                RecruitmentAccess::query(ReplacementRecord::class)
                    ->with(['client', 'jobRequirement', 'originalCandidate'])
                    ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
                    ->when($jobId, fn ($q) => $q->where('job_requirement_id', $jobId))
                    ->when($status, fn ($q) => $q->where('status', $status))
                    ->when($recruiterId, fn ($q) => $q->whereHas('joiningRecord', fn ($jq) => $jq->where('recruiter_id', $recruiterId)))
                    ->tap(fn ($q) => $applyDate($q, 'exit_date', true))
                    ->latest('exit_date')
                    ->get()
                    ->map(fn ($r) => [
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
                RecruitmentAccess::query(CandidateApplication::class)
                    ->with(['candidate', 'jobRequirement', 'client', 'recruiter'])
                    ->when($recruiterId, fn ($q) => $q->where('recruiter_id', $recruiterId))
                    ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
                    ->when($jobId, fn ($q) => $q->where('job_requirement_id', $jobId))
                    ->when($status, fn ($q) => $q->where('pipeline_stage', $status))
                    ->when($reportType === 'candidate_dropout' && ! $status, fn ($q) => $q->where(function ($dq) {
                        $dq->whereIn('pipeline_stage', ['dropped_out', 'did_not_join', 'not_interested'])
                            ->orWhereNotNull('dropout_reason');
                    }))
                    ->tap(fn ($q) => $applyDate($q, 'candidate_applications.created_at'))
                    ->orderByDesc('id')
                    ->limit(500)
                    ->get()
                    ->map(fn ($a) => [
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
