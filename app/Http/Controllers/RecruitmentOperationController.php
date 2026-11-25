<?php

namespace App\Http\Controllers;

use App\Models\AtsNotification;
use App\Models\CallLog;
use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\Client;
use App\Models\ClientFeedback;
use App\Models\ClientSubmission;
use App\Models\CommunicationRecord;
use App\Models\JobRequirement;
use App\Models\ScreeningRecord;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EmailService;
use App\Services\PipelineService;
use App\Services\RecruitmentAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RecruitmentOperationController extends Controller
{
    public function __construct(
        private readonly PipelineService $pipelineService,
        private readonly EmailService $emailService
    ) {}

    public function applications(Request $request)
    {
        $query = RecruitmentAccess::query(CandidateApplication::class)->with([
            'candidate.skills',
            'candidate.primaryResume',
            'jobRequirement.client',
            'recruiter',
            'clientSubmission',
            'interviewRecords',
            'offer',
            'joiningRecord',
        ]);

        if ($stage = $request->query('stage')) {
            $query->where('pipeline_stage', $stage);
        }
        if ($jobId = $request->query('job_id')) {
            $query->where('job_requirement_id', $jobId);
        }
        if ($clientId = $request->query('client_id')) {
            $query->where('client_id', $clientId);
        }
        if ($recruiterId = $request->query('recruiter_id')) {
            $query->where('recruiter_id', $recruiterId);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('application_code', 'like', "%{$search}%")
                    ->orWhereHas('candidate', fn ($cq) => $cq->where('full_name', 'like', "%{$search}%")->orWhere('candidate_code', 'like', "%{$search}%"))
                    ->orWhereHas('jobRequirement', fn ($jq) => $jq->where('title', 'like', "%{$search}%")->orWhere('job_code', 'like', "%{$search}%"));
            });
        }

        $applications = $query->orderByDesc('stage_updated_at')->paginate(25)->withQueryString();
        $jobs = RecruitmentAccess::query(JobRequirement::class)->with('client')->orderByDesc('id')->get();
        $clients = RecruitmentAccess::query(Client::class)->orderBy('company_name')->get();
        $recruiters = RecruitmentAccess::query(User::class)->whereHas('role', fn ($q) => $q->whereIn('slug', ['recruiter', 'team_leader']))->get();

        return view('operations.applications', compact('applications', 'jobs', 'clients', 'recruiters'));
    }

    public function storeApplication(Request $request)
    {
        $data = $request->validate([
            'candidate_id' => ['required', 'exists:candidates,id'],
            'job_requirement_id' => ['required', 'exists:job_requirements,id'],
            'expected_ctc' => ['nullable', 'numeric', 'min:0'],
            'notice_period_days' => ['nullable', 'integer', 'min:0'],
            'stage_remarks' => ['nullable', 'string'],
        ]);

        $candidate = RecruitmentAccess::query(Candidate::class)->findOrFail($data['candidate_id']);
        $job = RecruitmentAccess::query(JobRequirement::class)->findOrFail($data['job_requirement_id']);

        // Prevent duplicate effort: show when another recruiter is already working on the same candidate for the same vacancy
        $existingApp = RecruitmentAccess::query(CandidateApplication::class)->with('recruiter')
            ->where('candidate_id', $candidate->id)
            ->where('job_requirement_id', $job->id)
            ->first();

        if ($existingApp) {
            $ownerName = $existingApp->recruiter?->name ?? 'another recruiter';

            return back()->withErrors([
                'duplicate_effort' => "Duplicate effort prevented: {$candidate->full_name} is already associated with [{$job->job_code}] by {$ownerName} (Application {$existingApp->application_code}, Stage: {$existingApp->stageLabel()}).",
            ]);
        }

        $app = CandidateApplication::create([
            'candidate_id' => $candidate->id,
            'job_requirement_id' => $job->id,
            'client_id' => $job->client_id,
            'recruiter_id' => Auth::id(),
            'pipeline_stage' => 'sourced',
            'applied_ctc' => $candidate->current_ctc,
            'expected_ctc' => $data['expected_ctc'] ?? $candidate->expected_ctc,
            'currency' => $candidate->currency ?? 'INR',
            'notice_period_days' => $data['notice_period_days'] ?? $candidate->notice_period_days,
            'stage_remarks' => $data['stage_remarks'] ?? null,
        ]);

        AuditService::log('application.created', $app, $app->id, null, [
            'application_code' => $app->application_code,
            'candidate_id' => $candidate->id,
            'job_requirement_id' => $job->id,
        ]);

        AuditService::recordActivity(
            $candidate,
            'associated_with_job',
            "Associated with job {$job->job_code} ({$job->title}) as {$app->application_code}"
        );

        return back()->with('success', "Associated {$candidate->full_name} with [{$job->job_code}] ({$app->application_code}).");
    }

    public function storeCallLog(Request $request, Candidate $candidate)
    {
        $data = $request->validate([
            'job_requirement_id' => ['nullable', 'exists:job_requirements,id'],
            'candidate_application_id' => ['nullable', 'exists:candidate_applications,id'],
            'called_at' => ['nullable', 'date'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:14400'],
            'outcome' => ['required', 'string', 'in:'.implode(',', array_keys(CallLog::OUTCOMES))],
            'candidate_interest' => ['nullable', 'string', 'max:50'],
            'discussion_summary' => ['required', 'string', 'max:5000'],
            'screening_qa' => ['nullable', 'array'],
            'eligibility_remarks' => ['nullable', 'string', 'max:2000'],
            'current_ctc' => ['nullable', 'numeric', 'min:0'],
            'expected_ctc' => ['nullable', 'numeric', 'min:0'],
            'notice_period_days' => ['nullable', 'integer', 'min:0'],
            'interview_availability' => ['nullable', 'string', 'max:255'],
            'next_action' => ['nullable', 'string', 'max:120'],
            'follow_up_at' => ['nullable', 'date'],
        ]);

        return DB::transaction(function () use ($candidate, $data) {
            $appId = $data['candidate_application_id'] ?? null;
            if (! $appId && ! empty($data['job_requirement_id'])) {
                $app = RecruitmentAccess::query(CandidateApplication::class)->where('candidate_id', $candidate->id)
                    ->where('job_requirement_id', $data['job_requirement_id'])
                    ->first();
                $appId = $app?->id;
            }

            // Always create a new chronological call record; never overwrite previous call notes
            $call = CallLog::create([
                'candidate_id' => $candidate->id,
                'job_requirement_id' => $data['job_requirement_id'] ?? null,
                'candidate_application_id' => $appId,
                'recruiter_id' => Auth::id(),
                'called_at' => ! empty($data['called_at']) ? $data['called_at'] : now(),
                'duration_seconds' => $data['duration_seconds'] ?? null,
                'outcome' => $data['outcome'],
                'candidate_interest' => $data['candidate_interest'] ?? 'Interested',
                'discussion_summary' => $data['discussion_summary'],
                'screening_qa' => $data['screening_qa'] ?? null,
                'eligibility_remarks' => $data['eligibility_remarks'] ?? null,
                'current_ctc' => $data['current_ctc'] ?? $candidate->current_ctc,
                'expected_ctc' => $data['expected_ctc'] ?? $candidate->expected_ctc,
                'currency' => $candidate->currency ?? 'INR',
                'notice_period_days' => $data['notice_period_days'] ?? $candidate->notice_period_days,
                'interview_availability' => $data['interview_availability'] ?? null,
                'next_action' => $data['next_action'] ?? null,
                'follow_up_at' => $data['follow_up_at'] ?? null,
            ]);

            $candidate->update([
                'last_contacted_at' => $call->called_at,
                'next_follow_up_at' => $data['follow_up_at'] ?? $candidate->next_follow_up_at,
                'current_ctc' => $data['current_ctc'] ?? $candidate->current_ctc,
                'expected_ctc' => $data['expected_ctc'] ?? $candidate->expected_ctc,
                'notice_period_days' => $data['notice_period_days'] ?? $candidate->notice_period_days,
            ]);

            CommunicationRecord::create([
                'channel' => 'phone',
                'direction' => 'outbound',
                'candidate_id' => $candidate->id,
                'job_requirement_id' => $call->job_requirement_id,
                'user_id' => Auth::id(),
                'subject' => 'Call Discussion: '.(CallLog::OUTCOMES[$call->outcome] ?? $call->outcome),
                'summary' => $call->discussion_summary,
                'communicated_at' => $call->called_at,
            ]);

            if ($appId) {
                $app = RecruitmentAccess::query(CandidateApplication::class)->find($appId);
                if ($app && $app->pipeline_stage === 'sourced') {
                    $nextStage = match ($call->outcome) {
                        'interested' => 'interested',
                        'screening_completed' => 'screened',
                        'not_interested' => 'not_interested',
                        default => 'contacted',
                    };
                    $this->pipelineService->transitionStage($app, $nextStage, "Call outcome: {$call->outcome}");
                }
            }

            if (! empty($data['follow_up_at'])) {
                Task::create([
                    'title' => "Follow-up call with {$candidate->full_name}",
                    'description' => $data['next_action'] ?? $data['discussion_summary'],
                    'task_type' => 'call_candidate',
                    'assigned_to' => Auth::id(),
                    'created_by' => Auth::id(),
                    'candidate_id' => $candidate->id,
                    'job_requirement_id' => $call->job_requirement_id,
                    'candidate_application_id' => $appId,
                    'priority' => 'high',
                    'due_date' => $call->follow_up_at->toDateString(),
                    'due_time' => $call->follow_up_at->format('H:i'),
                    'status' => 'pending',
                ]);
            }

            AuditService::log('call.recorded', $call, $call->id, null, [
                'candidate_id' => $candidate->id,
                'outcome' => $call->outcome,
            ]);

            AuditService::recordActivity(
                $candidate,
                'call_recorded',
                'Recorded call discussion ('.(CallLog::OUTCOMES[$call->outcome] ?? $call->outcome).')',
                $call->discussion_summary
            );

            return back()->with('success', 'Call discussion logged in chronological communication history.');
        });
    }

    public function storeScreening(Request $request, CandidateApplication $application)
    {
        $data = $request->validate([
            'education_match' => ['nullable', 'boolean'],
            'experience_match' => ['nullable', 'boolean'],
            'skills_match' => ['nullable', 'boolean'],
            'salary_compatible' => ['nullable', 'boolean'],
            'location_compatible' => ['nullable', 'boolean'],
            'notice_period_compatible' => ['nullable', 'boolean'],
            'candidate_interest' => ['required', 'string', 'max:50'],
            'communication_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'question_answers' => ['nullable', 'array'],
            'screening_status' => ['required', 'in:passed,on_hold,not_eligible'],
            'assessment_summary' => ['required', 'string', 'max:4000'],
            'recruiter_recommendation' => ['nullable', 'string', 'max:2000'],
        ]);

        return DB::transaction(function () use ($request, $application, $data) {
            $screening = ScreeningRecord::create([
                'candidate_application_id' => $application->id,
                'candidate_id' => $application->candidate_id,
                'job_requirement_id' => $application->job_requirement_id,
                'recruiter_id' => Auth::id(),
                'education_match' => $request->boolean('education_match', true),
                'experience_match' => $request->boolean('experience_match', true),
                'skills_match' => $request->boolean('skills_match', true),
                'salary_compatible' => $request->boolean('salary_compatible', true),
                'location_compatible' => $request->boolean('location_compatible', true),
                'notice_period_compatible' => $request->boolean('notice_period_compatible', true),
                'candidate_interest' => $data['candidate_interest'],
                'communication_rating' => (int) $data['communication_rating'],
                'question_answers' => $data['question_answers'] ?? null,
                'screening_status' => $data['screening_status'],
                'assessment_summary' => $data['assessment_summary'],
                'recruiter_recommendation' => $data['recruiter_recommendation'] ?? null,
                'screened_at' => now(),
            ]);

            $targetStage = match ($data['screening_status']) {
                'passed' => 'screened',
                'on_hold' => 'on_hold',
                'not_eligible' => 'rejected',
            };

            if (in_array($application->pipeline_stage, ['sourced', 'contacted', 'interested', 'on_hold'], true)) {
                $this->pipelineService->transitionStage($application, $targetStage, $data['assessment_summary']);
            }

            AuditService::log('screening.completed', $screening, $screening->id, null, [
                'application_id' => $application->id,
                'screening_status' => $screening->screening_status,
            ]);

            return back()->with('success', "Screening checklist saved for {$application->candidate?->full_name}.");
        });
    }

    public function submissions(Request $request)
    {
        $query = RecruitmentAccess::query(ClientSubmission::class)->with(['candidate', 'jobRequirement', 'client', 'recruiter', 'resumeVersion']);

        if ($decision = $request->query('decision')) {
            $query->where('client_decision', $decision);
        }
        if ($clientId = $request->query('client_id')) {
            $query->where('client_id', $clientId);
        }
        if ($jobId = $request->query('job_id')) {
            $query->where('job_requirement_id', $jobId);
        }

        $submissions = $query->orderByDesc('submitted_at')->paginate(25)->withQueryString();
        $clients = RecruitmentAccess::query(Client::class)->orderBy('company_name')->get();
        $jobs = RecruitmentAccess::query(JobRequirement::class)->orderByDesc('id')->get();
        $screenedApplications = RecruitmentAccess::query(CandidateApplication::class)->with(['candidate', 'jobRequirement.client'])
            ->whereDoesntHave('clientSubmission')
            ->whereIn('pipeline_stage', ['interested', 'screened'])
            ->get();

        return view('operations.submissions', compact('submissions', 'clients', 'jobs', 'screenedApplications'));
    }

    public function submitProfile(Request $request, CandidateApplication $application)
    {
        $data = $request->validate([
            'screening_summary' => ['required', 'string', 'max:4000'],
            'candidate_availability' => ['nullable', 'string', 'max:255'],
            'recruiter_recommendation' => ['nullable', 'string', 'max:2000'],
            'client_visible_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Prevent duplicate submissions for the same candidate and job
        $existing = RecruitmentAccess::query(ClientSubmission::class)->where('candidate_id', $application->candidate_id)
            ->where('job_requirement_id', $application->job_requirement_id)
            ->first();

        if ($existing) {
            return back()->withErrors([
                'duplicate_submission' => "Duplicate submission blocked: {$application->candidate?->full_name} has already been submitted for this job requirement ({$existing->submission_code}).",
            ]);
        }

        return DB::transaction(function () use ($application, $data) {
            $candidate = $application->candidate()->with('primaryResume')->firstOrFail();
            $job = $application->jobRequirement()->with('client')->firstOrFail();

            $submission = ClientSubmission::create([
                'candidate_application_id' => $application->id,
                'job_requirement_id' => $job->id,
                'client_id' => $job->client_id,
                'candidate_id' => $candidate->id,
                'recruiter_id' => Auth::id() ?? $application->recruiter_id,
                'resume_version_id' => $candidate->primaryResume?->id,
                // Immutable snapshot of candidate details at submission time
                'snapshot_candidate_name' => $candidate->full_name,
                'snapshot_designation' => $candidate->current_designation,
                'snapshot_employer' => $candidate->current_employer,
                'snapshot_relevant_experience' => $candidate->relevant_experience,
                'snapshot_total_experience' => $candidate->total_experience,
                'snapshot_current_ctc' => $candidate->current_ctc,
                'snapshot_expected_ctc' => $application->expected_ctc ?? $candidate->expected_ctc,
                'snapshot_currency' => $candidate->currency ?? 'INR',
                'snapshot_notice_period_days' => $application->notice_period_days ?? $candidate->notice_period_days,
                'snapshot_location' => $candidate->current_location,
                'screening_summary' => $data['screening_summary'],
                'candidate_availability' => $data['candidate_availability'] ?? 'Within 48 hours notice',
                'recruiter_recommendation' => $data['recruiter_recommendation'] ?? 'Recommended for shortlist',
                'client_visible_notes' => $data['client_visible_notes'] ?? null,
                'client_decision' => 'pending_review',
                'submitted_at' => now(),
            ]);

            $this->pipelineService->transitionStage($application, 'submitted', 'Profile submitted to client');

            if ($job->status === 'assigned') {
                $job->update(['status' => 'sourcing_in_progress']);
            }

            if ($job->client?->primary_hr_email) {
                $this->emailService->sendTemplatedEmail(
                    'candidate_profile_submission',
                    $job->client->primary_hr_email,
                    $job->client->primary_hr_name,
                    [
                        'client_name' => $job->client->primary_hr_name,
                        'candidate_name' => $candidate->full_name,
                        'job_title' => $job->title,
                        'job_code' => $job->job_code,
                    ],
                    $submission
                );
            }

            AuditService::log('submission.created', $submission, $submission->id, null, [
                'submission_code' => $submission->submission_code,
                'candidate_id' => $candidate->id,
                'job_requirement_id' => $job->id,
            ]);

            return back()->with('success', "Submitted profile [{$submission->submission_code}] ({$candidate->full_name}) to {$job->client?->company_name}.");
        });
    }

    public function bulkSubmitProfiles(Request $request)
    {
        $data = $request->validate([
            'application_ids' => ['required', 'array', 'min:1'],
            'application_ids.*' => ['exists:candidate_applications,id'],
            'screening_summary' => ['required', 'string', 'max:2000'],
            'recruiter_recommendation' => ['nullable', 'string', 'max:1000'],
        ]);

        $submittedCount = 0;
        $skippedDuplicates = 0;

        DB::transaction(function () use ($data, &$submittedCount, &$skippedDuplicates) {
            foreach ($data['application_ids'] as $appId) {
                $app = RecruitmentAccess::query(CandidateApplication::class)->with(['candidate.primaryResume', 'jobRequirement'])->find($appId);
                if (! $app) {
                    continue;
                }

                $alreadySubmitted = RecruitmentAccess::query(ClientSubmission::class)->where('candidate_id', $app->candidate_id)
                    ->where('job_requirement_id', $app->job_requirement_id)
                    ->exists();

                if ($alreadySubmitted) {
                    $skippedDuplicates++;

                    continue;
                }

                $candidate = $app->candidate;
                ClientSubmission::create([
                    'candidate_application_id' => $app->id,
                    'job_requirement_id' => $app->job_requirement_id,
                    'client_id' => $app->client_id,
                    'candidate_id' => $candidate->id,
                    'recruiter_id' => Auth::id() ?? $app->recruiter_id,
                    'resume_version_id' => $candidate->primaryResume?->id,
                    'snapshot_candidate_name' => $candidate->full_name,
                    'snapshot_designation' => $candidate->current_designation,
                    'snapshot_employer' => $candidate->current_employer,
                    'snapshot_relevant_experience' => $candidate->relevant_experience,
                    'snapshot_total_experience' => $candidate->total_experience,
                    'snapshot_current_ctc' => $candidate->current_ctc,
                    'snapshot_expected_ctc' => $app->expected_ctc ?? $candidate->expected_ctc,
                    'snapshot_currency' => $candidate->currency ?? 'INR',
                    'snapshot_notice_period_days' => $app->notice_period_days ?? $candidate->notice_period_days,
                    'snapshot_location' => $candidate->current_location,
                    'screening_summary' => $data['screening_summary'],
                    'recruiter_recommendation' => $data['recruiter_recommendation'] ?? 'Recommended via bulk submission',
                    'client_decision' => 'pending_review',
                    'submitted_at' => now(),
                ]);

                $this->pipelineService->transitionStage($app, 'submitted', 'Bulk profile submission to client');
                $submittedCount++;
            }
        });

        return back()->with('success', "Bulk submission complete: {$submittedCount} profiles submitted, {$skippedDuplicates} duplicate(s) skipped.");
    }

    public function recordClientDecision(Request $request, ClientSubmission $submission)
    {
        $user = Auth::user();
        if ($user->isClient() && (int) $submission->client_id !== (int) $user->client_id) {
            abort(403, 'Unauthorised client organisation.');
        }

        $data = $request->validate([
            'client_decision' => ['required', 'in:pending_review,shortlisted,rejected,on_hold,additional_info_required,interview_requested'],
            'rejection_reason' => ['nullable', 'string', 'max:255'],
            'client_feedback_notes' => ['nullable', 'string', 'max:3000'],
            'preferred_interview_slots' => ['nullable', 'string', 'max:500'],
        ]);

        return DB::transaction(function () use ($user, $submission, $data) {
            $oldDecision = $submission->client_decision;

            $submission->update([
                'client_decision' => $data['client_decision'],
                'rejection_reason' => $data['rejection_reason'] ?? null,
                'client_feedback_notes' => $data['client_feedback_notes'] ?? null,
                'preferred_interview_slots' => $data['preferred_interview_slots'] ?? $submission->preferred_interview_slots,
                'decided_by' => $user->id,
                'decided_at' => now(),
            ]);

            ClientFeedback::create([
                'client_submission_id' => $submission->id,
                'client_id' => $submission->client_id,
                'user_id' => $user->id,
                'decision' => $data['client_decision'],
                'rejection_reason' => $data['rejection_reason'] ?? null,
                'remarks' => $data['client_feedback_notes'] ?? null,
                'recorded_by_recruiter' => ! $user->isClient(),
            ]);

            $app = $submission->candidateApplication;
            if ($app) {
                $targetStage = match ($data['client_decision']) {
                    'shortlisted', 'interview_requested' => 'client_shortlisted',
                    'rejected' => 'rejected',
                    'on_hold' => 'on_hold',
                    default => $app->pipeline_stage,
                };

                if ($targetStage !== $app->pipeline_stage) {
                    $this->pipelineService->transitionStage(
                        $app,
                        $targetStage,
                        $data['rejection_reason'] ?? $data['client_feedback_notes'] ?? "Client decision: {$data['client_decision']}"
                    );
                }
            }

            // Notify responsible recruiter when client provides feedback
            if ($submission->recruiter_id) {
                $decisionLabel = ClientSubmission::DECISIONS[$data['client_decision']] ?? $data['client_decision'];
                AtsNotification::sendToUser(
                    $submission->recruiter_id,
                    "Client Feedback: {$decisionLabel}",
                    "{$submission->client?->company_name} marked {$submission->snapshot_candidate_name} as [{$decisionLabel}] for {$submission->jobRequirement?->title}.",
                    'client_feedback',
                    "/submissions?job_id={$submission->job_requirement_id}"
                );
            }

            AuditService::log(
                'submission.decision_recorded',
                $submission,
                $submission->id,
                ['client_decision' => $oldDecision],
                ['client_decision' => $data['client_decision'], 'rejection_reason' => $data['rejection_reason'] ?? null]
            );

            return back()->with('success', 'Client decision and feedback recorded.');
        });
    }

    public function pipeline(Request $request)
    {
        $query = RecruitmentAccess::query(CandidateApplication::class)->with([
            'candidate.skills',
            'jobRequirement.client',
            'recruiter',
            'interviewRecords',
        ]);

        if ($jobId = $request->query('job_id')) {
            $query->where('job_requirement_id', $jobId);
        }
        if ($clientId = $request->query('client_id')) {
            $query->where('client_id', $clientId);
        }
        if ($recruiterId = $request->query('recruiter_id')) {
            $query->where('recruiter_id', $recruiterId);
        }
        if ($search = $request->query('search')) {
            $query->whereHas('candidate', fn ($q) => $q->where('full_name', 'like', "%{$search}%")->orWhere('candidate_code', 'like', "%{$search}%"));
        }

        $applications = $query->orderByDesc('stage_updated_at')->limit(300)->get();
        $groupedByStage = $applications->groupBy('pipeline_stage');

        $mainStages = CandidateApplication::MAIN_STAGES;
        $alternativeStates = CandidateApplication::ALTERNATIVE_STATES;
        $jobs = RecruitmentAccess::query(JobRequirement::class)->with('client')->orderByDesc('id')->get();
        $clients = RecruitmentAccess::query(Client::class)->orderBy('company_name')->get();
        $recruiters = RecruitmentAccess::query(User::class)->whereHas('role', fn ($q) => $q->whereIn('slug', ['recruiter', 'team_leader']))->get();
        $viewMode = $request->query('view', 'kanban');

        return view('operations.pipeline', compact(
            'applications',
            'groupedByStage',
            'mainStages',
            'alternativeStates',
            'jobs',
            'clients',
            'recruiters',
            'viewMode'
        ));
    }

    public function transitionStage(Request $request, CandidateApplication $application)
    {
        $data = $request->validate([
            'target_stage' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'lock_version' => ['nullable', 'integer'],
        ]);

        try {
            $updated = $this->pipelineService->transitionStage(
                $application,
                $data['target_stage'],
                $data['reason'] ?? null,
                isset($data['lock_version']) ? (int) $data['lock_version'] : null
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'application_id' => $updated->id,
                    'new_stage' => $updated->pipeline_stage,
                    'stage_label' => $updated->stageLabel(),
                    'lock_version' => $updated->lock_version,
                ]);
            }

            return back()->with('success', "Moved {$updated->candidate?->full_name} to [{$updated->stageLabel()}].");
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 409);
            }

            return back()->withErrors(['pipeline' => $e->getMessage()]);
        }
    }
}
