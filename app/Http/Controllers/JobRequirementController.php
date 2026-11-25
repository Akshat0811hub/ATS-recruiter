<?php

namespace App\Http\Controllers;

use App\Models\AtsNotification;
use App\Models\Client;
use App\Models\InterviewRoundDefinition;
use App\Models\JobAssignment;
use App\Models\JobRequirement;
use App\Models\JobScreeningQuestion;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EmailService;
use App\Services\RecruitmentAccess;
use App\Services\StorageManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class JobRequirementController extends Controller
{
    public function __construct(
        private readonly StorageManagementService $storageService,
        private readonly EmailService $emailService
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $query = RecruitmentAccess::query(JobRequirement::class)->with(['client', 'assignedTeam', 'assignments.recruiter'])
            ->withCount(['applications', 'submissions', 'interviews', 'offers', 'joinings']);

        if ($user->isRecruiter()) {
            $query->whereHas('assignments', fn ($q) => $q->where('recruiter_id', $user->id));
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('job_code', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($cq) => $cq->where('company_name', 'like', "%{$search}%"));
            });
        }
        if ($clientId = $request->query('client_id')) {
            $query->where('client_id', $clientId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }

        if ($request->boolean('active') || $request->boolean('overdue')) {
            $query->whereNotIn('status', ['draft', 'successfully_closed', 'cancelled', 'on_hold'])->where('remaining_count', '>', 0);
        }
        if ($request->boolean('overdue')) {
            $query->whereDate('expected_closure_date', '<', today()->toDateString());
        }

        $jobs = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $clients = RecruitmentAccess::query(Client::class)->where('status', 'active')->orderBy('company_name')->get();
        $teams = RecruitmentAccess::query(Team::class)->where('is_active', true)->get();
        $recruiters = RecruitmentAccess::query(User::class)->whereHas('role', fn ($q) => $q->whereIn('slug', ['recruiter', 'team_leader']))->where('is_active', true)->get();

        return view('jobs.index', compact('jobs', 'clients', 'teams', 'recruiters'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:200'],
            'openings_count' => ['required', 'integer', 'min:1', 'max:500'],
            'department' => ['nullable', 'string', 'max:120'],
            'industry' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'required_education' => ['nullable', 'string', 'max:255'],
            'required_skills_csv' => ['nullable', 'string'],
            'preferred_skills_csv' => ['nullable', 'string'],
            'min_experience' => ['required', 'numeric', 'min:0'],
            'max_experience' => ['nullable', 'numeric', 'min:0'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'location' => ['required', 'string', 'max:180'],
            'country' => ['required', 'string', 'max:100'],
            'work_mode' => ['required', 'in:onsite,hybrid,remote'],
            'employment_type' => ['required', 'in:full_time,contract,part_time,consultant'],
            'notice_period_preference' => ['nullable', 'string', 'max:60'],
            'priority' => ['required', 'in:critical,high,medium,low'],
            'expected_closure_date' => ['nullable', 'date'],
            'client_instructions' => ['nullable', 'string'],
            'internal_instructions' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'assigned_team_id' => ['nullable', 'exists:teams,id'],
            'jd_file' => ['nullable', 'file', 'max:10240'],
            'interview_rounds' => ['nullable', 'array'],
            'interview_rounds.*.name' => ['required_with:interview_rounds', 'string', 'max:150'],
            'interview_rounds.*.mode' => ['nullable', 'string', 'max:40'],
            'screening_questions' => ['nullable', 'array'],
            'screening_questions.*' => ['nullable', 'string', 'max:500'],
        ]);

        return DB::transaction(function () use ($request, $data) {
            $jdStorageId = null;
            if ($request->hasFile('jd_file')) {
                $rec = $this->storageService->storePrivateFile(
                    $request->file('jd_file'),
                    'jd',
                    JobRequirement::class
                );
                $jdStorageId = $rec->id;
            }

            $reqSkills = ! empty($data['required_skills_csv'])
                ? array_values(array_filter(array_map('trim', explode(',', $data['required_skills_csv']))))
                : [];
            $prefSkills = ! empty($data['preferred_skills_csv'])
                ? array_values(array_filter(array_map('trim', explode(',', $data['preferred_skills_csv']))))
                : [];

            $roundsInput = $data['interview_rounds'] ?? [
                ['name' => 'Round 1: HR Screening', 'mode' => 'telephonic'],
                ['name' => 'Round 2: Technical Interview', 'mode' => 'video'],
                ['name' => 'Round 3: Final Management & Offer Discussion', 'mode' => 'video'],
            ];

            $job = JobRequirement::create([
                'client_id' => $data['client_id'],
                'title' => $data['title'],
                'openings_count' => (int) $data['openings_count'],
                'joined_count' => 0,
                'remaining_count' => (int) $data['openings_count'],
                'department' => $data['department'] ?? null,
                'industry' => $data['industry'] ?? null,
                'description' => $data['description'] ?? null,
                'jd_storage_id' => $jdStorageId,
                'required_education' => $data['required_education'] ?? null,
                'required_skills' => $reqSkills,
                'preferred_skills' => $prefSkills,
                'min_experience' => $data['min_experience'],
                'max_experience' => $data['max_experience'] ?? null,
                'min_salary' => $data['min_salary'] ?? null,
                'max_salary' => $data['max_salary'] ?? null,
                'currency' => $data['currency'],
                'location' => $data['location'],
                'country' => $data['country'],
                'work_mode' => $data['work_mode'],
                'employment_type' => $data['employment_type'],
                'notice_period_preference' => $data['notice_period_preference'] ?? 'Immediate to 30 Days',
                'priority' => $data['priority'],
                'expected_closure_date' => $data['expected_closure_date'] ?? null,
                'interview_rounds_count' => count($roundsInput),
                'client_instructions' => $data['client_instructions'] ?? null,
                'internal_instructions' => $data['internal_instructions'] ?? null,
                'status' => $data['status'] ?? 'approved',
                'assigned_team_id' => $data['assigned_team_id'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach (array_values($roundsInput) as $idx => $r) {
                InterviewRoundDefinition::create([
                    'job_requirement_id' => $job->id,
                    'round_number' => $idx + 1,
                    'round_name' => $r['name'],
                    'interview_mode' => $r['mode'] ?? 'video',
                    'is_mandatory' => true,
                ]);
            }

            foreach (array_filter($data['screening_questions'] ?? []) as $qIdx => $questionText) {
                JobScreeningQuestion::create([
                    'job_requirement_id' => $job->id,
                    'question' => $questionText,
                    'question_type' => 'text',
                    'is_mandatory' => true,
                    'sort_order' => $qIdx + 1,
                ]);
            }

            AuditService::log('job.created', $job, $job->id, null, [
                'job_code' => $job->job_code,
                'title' => $job->title,
                'openings_count' => $job->openings_count,
            ]);

            $client = $job->client;
            if ($client && $client->primary_hr_email) {
                $this->emailService->sendTemplatedEmail(
                    'client_requirement_acknowledgement',
                    $client->primary_hr_email,
                    $client->primary_hr_name,
                    [
                        'client_name' => $client->primary_hr_name,
                        'job_title' => $job->title,
                        'job_code' => $job->job_code,
                        'openings_count' => $job->openings_count,
                    ],
                    $job
                );
            }

            return redirect()->route('jobs.show', $job)->with('success', "Job Requirement [{$job->title}] ({$job->job_code}) created.");
        });
    }

    public function show(JobRequirement $job)
    {
        $user = Auth::user();
        if ($user->isRecruiter() && ! $job->assignments()->where('recruiter_id', $user->id)->exists()) {
            abort(403, 'You are not assigned to this job requirement.');
        }

        $job->load([
            'client',
            'jdStorage',
            'assignedTeam',
            'assignments.recruiter',
            'screeningQuestions',
            'interviewRoundDefinitions',
            'applications.candidate',
            'applications.recruiter',
            'submissions',
            'interviews',
            'offers',
            'joinings',
        ]);

        $recruiters = RecruitmentAccess::query(User::class)->whereHas('role', fn ($q) => $q->whereIn('slug', ['recruiter', 'team_leader']))
            ->where('is_active', true)
            ->withCount('jobAssignments')
            ->get();

        return view('jobs.show', compact('job', 'recruiters'));
    }

    public function update(Request $request, JobRequirement $job)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'openings_count' => ['required', 'integer', 'min:1', 'max:500'],
            'department' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'required_education' => ['nullable', 'string', 'max:255'],
            'required_skills_csv' => ['nullable', 'string'],
            'min_experience' => ['required', 'numeric', 'min:0'],
            'max_experience' => ['nullable', 'numeric', 'min:0'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'min:0'],
            'location' => ['required', 'string', 'max:180'],
            'work_mode' => ['required', 'in:onsite,hybrid,remote'],
            'priority' => ['required', 'in:critical,high,medium,low'],
            'expected_closure_date' => ['nullable', 'date'],
            'status' => ['required', 'string'],
            'client_instructions' => ['nullable', 'string'],
            'internal_instructions' => ['nullable', 'string'],
            'jd_file' => ['nullable', 'file', 'max:10240'],
        ]);

        if ($request->hasFile('jd_file')) {
            $rec = $this->storageService->storePrivateFile(
                $request->file('jd_file'),
                'jd',
                JobRequirement::class,
                $job->id
            );
            $job->jd_storage_id = $rec->id;
        }

        if (isset($data['required_skills_csv'])) {
            $job->required_skills = array_values(array_filter(array_map('trim', explode(',', (string) $data['required_skills_csv']))));
        }

        $oldStatus = $job->status;
        $job->fill(collect($data)->except(['jd_file', 'required_skills_csv'])->toArray());
        $job->remaining_count = max(0, (int) $job->openings_count - (int) $job->joined_count);
        $job->save();

        if ($oldStatus !== $job->status) {
            AuditService::recordStatusTransition($job, $oldStatus, $job->status, 'Updated job status');
        }
        AuditService::log('job.updated', $job, $job->id, ['status' => $oldStatus], ['status' => $job->status, 'openings_count' => $job->openings_count]);

        return back()->with('success', "Job Requirement [{$job->job_code}] updated.");
    }

    public function assignRecruiters(Request $request, JobRequirement $job)
    {
        $data = $request->validate([
            'primary_recruiter_id' => ['required', 'exists:users,id'],
            'secondary_recruiter_ids' => ['nullable', 'array'],
            'secondary_recruiter_ids.*' => ['exists:users,id'],
            'deadline_date' => ['nullable', 'date'],
            'daily_sourcing_target' => ['required', 'integer', 'min:1'],
            'submission_target' => ['required', 'integer', 'min:1'],
            'priority' => ['required', 'in:critical,high,medium,low'],
            'internal_instructions' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($job, $data) {
            $allRecruiterIds = array_unique(array_merge(
                [(int) $data['primary_recruiter_id']],
                array_map('intval', $data['secondary_recruiter_ids'] ?? [])
            ));

            foreach ($allRecruiterIds as $recruiterId) {
                $isPrimary = $recruiterId === (int) $data['primary_recruiter_id'];

                JobAssignment::updateOrCreate(
                    [
                        'job_requirement_id' => $job->id,
                        'recruiter_id' => $recruiterId,
                    ],
                    [
                        'assigned_by' => Auth::id(),
                        'is_primary' => $isPrimary,
                        'assigned_at' => now(),
                        'deadline_date' => $data['deadline_date'] ?? null,
                        'daily_sourcing_target' => $data['daily_sourcing_target'],
                        'submission_target' => $data['submission_target'],
                        'priority' => $data['priority'],
                        'status' => 'active',
                        'internal_instructions' => $data['internal_instructions'] ?? null,
                    ]
                );

                $recruiter = RecruitmentAccess::query(User::class)->find($recruiterId);
                if ($recruiter) {
                    AtsNotification::sendToUser(
                        $recruiter,
                        "New Requirement Assigned: {$job->title}",
                        "You have been assigned to {$job->job_code} ({$job->client?->company_name}). Target: {$data['submission_target']} submissions.",
                        'job_assignment',
                        "/jobs/{$job->id}"
                    );

                    $this->emailService->sendTemplatedEmail(
                        'recruiter_assignment',
                        $recruiter->email,
                        $recruiter->name,
                        [
                            'recruiter_name' => $recruiter->name,
                            'job_title' => $job->title,
                            'job_code' => $job->job_code,
                            'client_name' => $job->client?->company_name ?? '',
                            'submission_target' => $data['submission_target'],
                        ],
                        $job
                    );
                }
            }

            if (in_array($job->status, ['draft', 'new_requirement', 'pending_review', 'approved'], true)) {
                $job->update(['status' => 'assigned']);
            }

            AuditService::log('job.recruiters_assigned', $job, $job->id, null, [
                'assigned_recruiters' => $allRecruiterIds,
                'primary_recruiter_id' => $data['primary_recruiter_id'],
            ]);
        });

        return back()->with('success', 'Recruiters assigned and notified immediately.');
    }

    public function addInterviewRoundDefinition(Request $request, JobRequirement $job)
    {
        $data = $request->validate([
            'round_name' => ['required', 'string', 'max:150'],
            'interview_mode' => ['required', 'in:telephonic,video,face_to_face,technical_assessment,online_assessment'],
            'default_duration_minutes' => ['required', 'integer', 'min:10', 'max:360'],
        ]);

        $nextRoundNumber = ((int) $job->interviewRoundDefinitions()->max('round_number')) + 1;

        $round = InterviewRoundDefinition::create([
            'job_requirement_id' => $job->id,
            'round_number' => $nextRoundNumber,
            'round_name' => $data['round_name'],
            'interview_mode' => $data['interview_mode'],
            'default_duration_minutes' => $data['default_duration_minutes'],
            'is_mandatory' => true,
        ]);

        $job->update(['interview_rounds_count' => $nextRoundNumber]);

        AuditService::log('job.interview_round_added', $job, $job->id, null, $round->toArray());

        return back()->with('success', "Added interview stage [{$round->round_name}] (Round {$nextRoundNumber}).");
    }

    /**
     * Duplicate a previous requirement without duplicating its candidate applications.
     */
    public function duplicate(JobRequirement $job)
    {
        return DB::transaction(function () use ($job) {
            $clone = $job->replicate(['job_code', 'joined_count', 'remaining_count', 'status', 'approved_at', 'closed_at']);
            $clone->job_code = null; // Will be auto-generated by booted hook
            $clone->joined_count = 0;
            $clone->remaining_count = $job->openings_count;
            $clone->status = 'new_requirement';
            $clone->duplicated_from_id = $job->id;
            $clone->created_by = Auth::id();
            $clone->save();

            foreach ($job->interviewRoundDefinitions as $roundDef) {
                InterviewRoundDefinition::create([
                    'job_requirement_id' => $clone->id,
                    'round_number' => $roundDef->round_number,
                    'round_name' => $roundDef->round_name,
                    'interview_mode' => $roundDef->interview_mode,
                    'default_duration_minutes' => $roundDef->default_duration_minutes,
                    'is_mandatory' => $roundDef->is_mandatory,
                ]);
            }

            foreach ($job->screeningQuestions as $sq) {
                JobScreeningQuestion::create([
                    'job_requirement_id' => $clone->id,
                    'question' => $sq->question,
                    'question_type' => $sq->question_type,
                    'options' => $sq->options,
                    'expected_answer' => $sq->expected_answer,
                    'is_mandatory' => $sq->is_mandatory,
                    'sort_order' => $sq->sort_order,
                ]);
            }

            AuditService::log('job.duplicated', $clone, $clone->id, ['source_job_id' => $job->id], ['new_job_code' => $clone->job_code]);

            return redirect()->route('jobs.show', $clone)->with('success', "Duplicated requirement as [{$clone->job_code}] with 0 candidate applications.");
        });
    }
}
