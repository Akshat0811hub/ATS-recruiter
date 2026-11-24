<?php

namespace App\Http\Controllers;

use App\Models\AtsNotification;
use App\Models\CandidateApplication;
use App\Models\Client;
use App\Models\InterviewAttempt;
use App\Models\InterviewFeedback;
use App\Models\InterviewRecord;
use App\Models\InterviewRoundDefinition;
use App\Models\JobRequirement;
use App\Services\AuditService;
use App\Services\EmailService;
use App\Services\PipelineService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InterviewController extends Controller
{
    public function __construct(
        private readonly PipelineService $pipelineService,
        private readonly EmailService $emailService
    ) {}

    public function index(Request $request)
    {
        $query = InterviewRecord::with([
            'candidate',
            'jobRequirement',
            'client',
            'candidateApplication',
            'attempts',
            'feedbackEntries',
        ]);

        if ($date = $request->query('date')) {
            $query->whereDate('interview_date', $date);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($clientId = $request->query('client_id')) {
            $query->where('client_id', $clientId);
        }
        if ($jobId = $request->query('job_id')) {
            $query->where('job_requirement_id', $jobId);
        }

        $interviews = $query->orderByDesc('interview_date')->orderBy('round_number')->paginate(25)->withQueryString();
        $clients = Client::orderBy('company_name')->get();
        $jobs = JobRequirement::with('interviewRoundDefinitions')->orderByDesc('id')->get();
        $eligibleApplications = CandidateApplication::with(['candidate', 'jobRequirement.client', 'jobRequirement.interviewRoundDefinitions', 'interviewRecords'])
            ->whereIn('pipeline_stage', ['submitted', 'client_shortlisted', 'interview_scheduled', 'interview_in_progress'])
            ->orderByDesc('id')
            ->get();

        return view('interviews.index', compact('interviews', 'clients', 'jobs', 'eligibleApplications'));
    }

    public function scheduleRound(Request $request, CandidateApplication $application)
    {
        $data = $request->validate([
            'round_number' => ['required', 'integer', 'min:1', 'max:50'],
            'round_name' => ['nullable', 'string', 'max:150'],
            'interviewer_name' => ['required', 'string', 'max:150'],
            'interviewer_email' => ['nullable', 'email', 'max:180'],
            'interview_date' => ['required', 'date'],
            'interview_time' => ['required', 'string', 'max:20'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:480'],
            'mode' => ['required', 'in:telephonic,video,face_to_face,technical_assessment,online_assessment'],
            'meeting_link' => ['nullable', 'string', 'max:500'],
            'physical_location' => ['nullable', 'string', 'max:300'],
            'candidate_availability' => ['nullable', 'string', 'max:255'],
            'client_availability' => ['nullable', 'string', 'max:255'],
            'candidate_confirmed' => ['nullable', 'boolean'],
            'client_confirmed' => ['nullable', 'boolean'],
            'interview_instructions' => ['nullable', 'string'],
            'recruiter_remarks' => ['nullable', 'string'],
            'reschedule_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $roundNumber = (int) $data['round_number'];

        // Verify candidate has not failed an earlier round without authorised override
        $failedEarlierRound = $application->interviewRecords()
            ->where('round_number', '<', $roundNumber)
            ->where('status', 'failed')
            ->where('is_overridden', false)
            ->first();

        if ($failedEarlierRound) {
            return back()->withErrors([
                'interview_progression' => "Unauthorised progression blocked: Candidate failed Round {$failedEarlierRound->round_number} ({$failedEarlierRound->round_name}). An authorised override with mandatory reason is required before scheduling Round {$roundNumber}.",
            ]);
        }

        $scheduledAt = Carbon::parse($data['interview_date'].' '.$data['interview_time']);

        // Conflict Detection for Candidate at the exact scheduled timestamp
        $candidateConflict = InterviewRecord::where('candidate_id', $application->candidate_id)
            ->where('candidate_application_id', '!=', $application->id)
            ->whereDate('interview_date', $data['interview_date'])
            ->where('interview_time', $data['interview_time'])
            ->whereIn('status', ['scheduled', 'rescheduled'])
            ->exists();

        if ($candidateConflict) {
            return back()->withErrors([
                'conflict' => "Schedule conflict detected: {$application->candidate?->full_name} already has another interview scheduled on {$data['interview_date']} at {$data['interview_time']}.",
            ]);
        }

        return DB::transaction(function () use ($request, $application, $data, $roundNumber, $scheduledAt) {
            $roundDef = InterviewRoundDefinition::where('job_requirement_id', $application->job_requirement_id)
                ->where('round_number', $roundNumber)
                ->first();

            $roundName = $data['round_name'] ?: ($roundDef?->round_name ?? "Round {$roundNumber}: Interview");

            $existingRecord = InterviewRecord::where('candidate_application_id', $application->id)
                ->where('round_number', $roundNumber)
                ->first();

            $isReschedule = $existingRecord !== null;
            $attemptCount = $isReschedule ? ($existingRecord->attempts_count + 1) : 1;

            $interview = InterviewRecord::updateOrCreate(
                [
                    'candidate_application_id' => $application->id,
                    'round_number' => $roundNumber,
                ],
                [
                    'job_requirement_id' => $application->job_requirement_id,
                    'client_id' => $application->client_id,
                    'candidate_id' => $application->candidate_id,
                    'interview_round_definition_id' => $roundDef?->id,
                    'round_name' => $roundName,
                    'interviewer_name' => $data['interviewer_name'],
                    'interviewer_email' => $data['interviewer_email'] ?? null,
                    'interview_date' => $data['interview_date'],
                    'interview_time' => $data['interview_time'],
                    'scheduled_at' => $scheduledAt,
                    'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
                    'duration_minutes' => $data['duration_minutes'],
                    'mode' => $data['mode'],
                    'meeting_link' => $data['meeting_link'] ?? null,
                    'physical_location' => $data['physical_location'] ?? null,
                    'candidate_availability' => $data['candidate_availability'] ?? null,
                    'client_availability' => $data['client_availability'] ?? null,
                    'candidate_confirmed' => $request->boolean('candidate_confirmed', true),
                    'client_confirmed' => $request->boolean('client_confirmed', true),
                    'interview_instructions' => $data['interview_instructions'] ?? null,
                    'recruiter_remarks' => $data['recruiter_remarks'] ?? null,
                    'status' => $isReschedule ? 'rescheduled' : 'scheduled',
                    'attempts_count' => $attemptCount,
                    'scheduled_by' => Auth::id(),
                ]
            );

            InterviewAttempt::create([
                'interview_record_id' => $interview->id,
                'attempt_number' => $attemptCount,
                'scheduled_at' => $scheduledAt,
                'timezone' => $interview->timezone,
                'mode' => $interview->mode,
                'interviewer_name' => $interview->interviewer_name,
                'status' => $isReschedule ? 'rescheduled' : 'scheduled',
                'reason' => $data['reschedule_reason'] ?? ($isReschedule ? 'Rescheduled interview slot' : 'Initial interview schedule'),
                'remarks' => $data['recruiter_remarks'] ?? null,
                'recorded_by' => Auth::id(),
            ]);

            $application->update([
                'current_interview_round' => max((int) $application->current_interview_round, $roundNumber),
            ]);

            $targetStage = $roundNumber > 1 ? 'interview_in_progress' : 'interview_scheduled';
            if (! in_array($application->pipeline_stage, ['selected', 'offer_released', 'offer_accepted', 'joining_pending', 'joined'], true)) {
                $this->pipelineService->transitionStage(
                    $application,
                    $targetStage,
                    ($isReschedule ? 'Rescheduled ' : 'Scheduled ').$roundName
                );
            }

            if ($application->jobRequirement?->status === 'sourcing_in_progress') {
                $application->jobRequirement->update(['status' => 'interview_in_progress']);
            }

            if ($application->candidate?->email) {
                $this->emailService->sendTemplatedEmail(
                    $isReschedule ? 'interview_rescheduling' : 'interview_invitation',
                    $application->candidate->email,
                    $application->candidate->full_name,
                    [
                        'candidate_name' => $application->candidate->full_name,
                        'round_name' => $roundName,
                        'job_title' => $application->jobRequirement?->title ?? '',
                        'client_name' => $application->client?->company_name ?? '',
                        'interview_date' => $data['interview_date'],
                        'interview_time' => $data['interview_time'],
                        'mode' => InterviewRecord::MODES[$data['mode']] ?? $data['mode'],
                    ],
                    $interview
                );
            }

            AuditService::log(
                $isReschedule ? 'interview.rescheduled' : 'interview.scheduled',
                $interview,
                $interview->id,
                null,
                [
                    'round_number' => $roundNumber,
                    'round_name' => $roundName,
                    'attempt_number' => $attemptCount,
                    'scheduled_at' => $scheduledAt->toIso8601String(),
                ]
            );

            return back()->with('success', ($isReschedule ? 'Rescheduled ' : 'Scheduled ')."{$roundName} (Attempt #{$attemptCount}) for {$application->candidate?->full_name}.");
        });
    }

    public function recordRoundResult(Request $request, InterviewRecord $interview)
    {
        $user = Auth::user();
        if ($user->isClient() && (int) $interview->client_id !== (int) $user->client_id) {
            abort(403, 'Unauthorised client organisation.');
        }

        $data = $request->validate([
            'status' => ['required', 'in:completed,passed,failed,on_hold,no_show,cancelled'],
            'technical_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'communication_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'role_fit_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'feedback_summary' => ['required', 'string', 'max:4000'],
            'strengths' => ['nullable', 'string', 'max:2000'],
            'areas_of_concern' => ['nullable', 'string', 'max:2000'],
            'next_action' => ['nullable', 'string', 'max:150'],
        ]);

        return DB::transaction(function () use ($user, $interview, $data) {
            $oldStatus = $interview->status;
            $result = in_array($data['status'], ['passed', 'failed', 'on_hold'], true) ? $data['status'] : 'pending';

            $interview->update([
                'status' => $data['status'],
                'result' => $result,
                'score' => $data['score'] ?? null,
                'feedback_summary' => $data['feedback_summary'],
                'next_action' => $data['next_action'] ?? null,
            ]);

            InterviewFeedback::create([
                'interview_record_id' => $interview->id,
                'submitted_by' => $user->id,
                'evaluator_name' => $interview->interviewer_name ?: $user->name,
                'technical_score' => $data['technical_score'] ?? null,
                'communication_score' => $data['communication_score'] ?? null,
                'role_fit_score' => $data['role_fit_score'] ?? null,
                'overall_score' => $data['score'] ?? null,
                'result' => $result,
                'strengths' => $data['strengths'] ?? null,
                'areas_of_concern' => $data['areas_of_concern'] ?? null,
                'remarks' => $data['feedback_summary'],
            ]);

            $app = $interview->candidateApplication;
            if ($app) {
                if ($data['status'] === 'failed') {
                    // Retain all earlier round information; mark application as rejected unless overridden later
                    $this->pipelineService->transitionStage(
                        $app,
                        'rejected',
                        "Failed {$interview->round_name}: {$data['feedback_summary']}"
                    );
                } elseif ($data['status'] === 'passed') {
                    $totalConfiguredRounds = (int) ($app->jobRequirement?->interview_rounds_count ?? 1);
                    $passedCount = $app->interviewRecords()->whereIn('status', ['passed', 'skipped_with_authorisation'])->count();

                    if ($interview->round_number >= $totalConfiguredRounds && $passedCount >= $totalConfiguredRounds) {
                        $this->pipelineService->transitionStage(
                            $app,
                            'selected',
                            "Passed final interview round ({$interview->round_name})"
                        );
                    } else {
                        $this->pipelineService->transitionStage(
                            $app,
                            'interview_in_progress',
                            "Passed {$interview->round_name}"
                        );
                    }
                }
            }

            if ($app?->recruiter_id) {
                AtsNotification::sendToUser(
                    $app->recruiter_id,
                    "Interview Result: {$interview->round_name} — ".strtoupper($data['status']),
                    "{$interview->candidate?->full_name} marked as [{$data['status']}] in {$interview->round_name}.",
                    'interview_result',
                    "/interviews?job_id={$interview->job_requirement_id}"
                );
            }

            AuditService::log(
                'interview.result_recorded',
                $interview,
                $interview->id,
                ['status' => $oldStatus],
                ['status' => $data['status'], 'score' => $data['score'] ?? null],
                $data['feedback_summary']
            );

            return back()->with('success', "Recorded result [{$data['status']}] for {$interview->round_name}.");
        });
    }

    /**
     * Allow authorised Super Admin / Manager override of a failed or skipped interview round
     * with mandatory reason and immutable audit history.
     */
    public function overrideRound(Request $request, InterviewRecord $interview)
    {
        $data = $request->validate([
            'override_reason' => ['required', 'string', 'min:10', 'max:1000'],
            'new_status' => ['nullable', 'in:passed,skipped_with_authorisation'],
        ]);

        return DB::transaction(function () use ($interview, $data) {
            $oldStatus = $interview->status;
            $newStatus = $data['new_status'] ?? 'skipped_with_authorisation';

            $interview->update([
                'status' => $newStatus,
                'result' => 'passed',
                'is_overridden' => true,
                'override_by' => Auth::id(),
                'override_reason' => $data['override_reason'],
            ]);

            $app = $interview->candidateApplication;
            if ($app && $app->pipeline_stage === 'rejected') {
                $this->pipelineService->transitionStage(
                    $app,
                    'interview_in_progress',
                    "Authorised override on {$interview->round_name}: {$data['override_reason']}",
                    null,
                    true
                );
            }

            AuditService::log(
                'interview.authorised_override',
                $interview,
                $interview->id,
                ['status' => $oldStatus, 'is_overridden' => false],
                ['status' => $newStatus, 'is_overridden' => true, 'override_reason' => $data['override_reason']],
                $data['override_reason']
            );

            return back()->with('success', "Authorised override applied to {$interview->round_name} with audit history.");
        });
    }
}
