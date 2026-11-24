<?php

namespace App\Services;

use App\Models\AtsNotification;
use App\Models\CandidateApplication;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PipelineService
{
    /**
     * Transition a candidate application to a new pipeline stage within a database transaction.
     * Enforces optimistic locking, failed-round blocks, status history, and automatic follow-up tasks.
     */
    public function transitionStage(
        CandidateApplication $application,
        string $targetStage,
        ?string $reason = null,
        ?int $expectedLockVersion = null,
        bool $allowAuthorisedOverride = false
    ): CandidateApplication {
        $validStages = array_keys(CandidateApplication::allStages());
        if (! in_array($targetStage, $validStages, true)) {
            throw new RuntimeException("Invalid pipeline stage [{$targetStage}].");
        }

        return DB::transaction(function () use ($application, $targetStage, $reason, $expectedLockVersion, $allowAuthorisedOverride) {
            $lockedApp = CandidateApplication::lockForUpdate()->findOrFail($application->id);

            if ($expectedLockVersion !== null && (int) $lockedApp->lock_version !== $expectedLockVersion) {
                throw new RuntimeException('Conflicting update detected: Another user updated this application. Please refresh and review.');
            }

            // Block progression to selected/offer/joining if any interview round has failed without authorised override
            $postInterviewStages = ['selected', 'offer_released', 'offer_accepted', 'joining_pending', 'joined'];
            if (in_array($targetStage, $postInterviewStages, true) && ! $allowAuthorisedOverride) {
                $hasUnoverriddenFailedRound = $lockedApp->interviewRecords()
                    ->where('status', 'failed')
                    ->where('is_overridden', false)
                    ->exists();

                if ($hasUnoverriddenFailedRound) {
                    throw new RuntimeException('Cannot progress candidate past failed interview round without an authorised override and mandatory audit reason.');
                }
            }

            $previousStage = $lockedApp->pipeline_stage;
            $inactiveStages = array_keys(CandidateApplication::ALTERNATIVE_STATES);

            $lockedApp->update([
                'pipeline_stage' => $targetStage,
                'stage_updated_at' => now(),
                'is_active' => ! in_array($targetStage, $inactiveStages, true),
                'rejection_reason' => $targetStage === 'rejected' ? ($reason ?? $lockedApp->rejection_reason) : $lockedApp->rejection_reason,
                'dropout_reason' => in_array($targetStage, ['dropped_out', 'did_not_join', 'not_interested'], true) ? ($reason ?? $lockedApp->dropout_reason) : $lockedApp->dropout_reason,
                'stage_remarks' => $reason ?? $lockedApp->stage_remarks,
                'lock_version' => $lockedApp->lock_version + 1,
            ]);

            $lockedApp->candidate()->update([
                'current_stage' => $targetStage,
            ]);

            AuditService::recordStatusTransition(
                $lockedApp,
                $previousStage,
                $targetStage,
                $reason ?? "Pipeline stage moved from {$previousStage} to {$targetStage}"
            );

            AuditService::recordActivity(
                $lockedApp->candidate,
                'stage_changed',
                "Application {$lockedApp->application_code} moved to ".CandidateApplication::allStages()[$targetStage],
                $reason,
                in_array($targetStage, ['submitted', 'client_shortlisted', 'interview_scheduled', 'selected', 'offer_released', 'offer_accepted', 'joined'], true)
            );

            AuditService::log(
                'pipeline.stage_transition',
                $lockedApp,
                $lockedApp->id,
                ['pipeline_stage' => $previousStage],
                ['pipeline_stage' => $targetStage],
                $reason
            );

            $this->generateAutomatedFollowUpTask($lockedApp, $targetStage);

            if ($lockedApp->recruiter_id && $lockedApp->recruiter_id !== Auth::id()) {
                AtsNotification::sendToUser(
                    $lockedApp->recruiter_id,
                    'Candidate Stage Updated',
                    "{$lockedApp->candidate?->full_name} moved to ".CandidateApplication::allStages()[$targetStage]." for {$lockedApp->jobRequirement?->title}.",
                    'pipeline_update',
                    "/applications/{$lockedApp->id}"
                );
            }

            return $lockedApp->fresh();
        });
    }

    /**
     * Automatically create relevant follow-up tasks when recruitment stages change,
     * using auto_trigger_key to prevent duplicate automated tasks.
     */
    public function generateAutomatedFollowUpTask(CandidateApplication $app, string $stage): ?Task
    {
        $taskBlueprints = [
            'interested' => [
                'type' => 'screen_candidate',
                'title' => "Complete job screening checklist for {$app->candidate?->full_name}",
                'days' => 1,
                'priority' => 'high',
            ],
            'screened' => [
                'type' => 'submit_profile',
                'title' => "Submit screened profile of {$app->candidate?->full_name} to client",
                'days' => 1,
                'priority' => 'high',
            ],
            'submitted' => [
                'type' => 'request_client_feedback',
                'title' => "Follow up with {$app->client?->company_name} on submitted profile {$app->candidate?->full_name}",
                'days' => 2,
                'priority' => 'medium',
            ],
            'client_shortlisted' => [
                'type' => 'confirm_interview',
                'title' => "Schedule & confirm interview for shortlisted candidate {$app->candidate?->full_name}",
                'days' => 1,
                'priority' => 'high',
            ],
            'interview_scheduled' => [
                'type' => 'collect_interview_results',
                'title' => "Collect interview results & feedback for {$app->candidate?->full_name}",
                'days' => 1,
                'priority' => 'high',
            ],
            'offer_released' => [
                'type' => 'follow_up_on_offer',
                'title' => "Follow up on offer acceptance with {$app->candidate?->full_name}",
                'days' => 2,
                'priority' => 'critical',
            ],
            'offer_accepted' => [
                'type' => 'confirm_joining',
                'title' => "Pre-joining readiness & notice tracking for {$app->candidate?->full_name}",
                'days' => 3,
                'priority' => 'critical',
            ],
            'joined' => [
                'type' => 'post_joining_follow_up',
                'title' => "30-day post-joining retention check-in for {$app->candidate?->full_name}",
                'days' => 30,
                'priority' => 'medium',
            ],
        ];

        if (! isset($taskBlueprints[$stage])) {
            return null;
        }

        $blueprint = $taskBlueprints[$stage];
        $assigneeId = $app->recruiter_id ?? Auth::id();
        if (! $assigneeId) {
            return null;
        }

        $triggerKey = "app_{$app->id}_stage_{$stage}";
        $existingTask = Task::where('auto_trigger_key', $triggerKey)
            ->whereIn('status', ['pending', 'in_progress'])
            ->first();

        if ($existingTask) {
            return $existingTask;
        }

        return Task::create([
            'title' => $blueprint['title'],
            'description' => "Automated workflow task triggered when application {$app->application_code} entered stage [{$stage}].",
            'task_type' => $blueprint['type'],
            'assigned_to' => $assigneeId,
            'created_by' => Auth::id() ?? $assigneeId,
            'candidate_id' => $app->candidate_id,
            'client_id' => $app->client_id,
            'job_requirement_id' => $app->job_requirement_id,
            'candidate_application_id' => $app->id,
            'priority' => $blueprint['priority'],
            'due_date' => now()->addDays($blueprint['days'])->toDateString(),
            'due_time' => '11:00',
            'status' => 'pending',
            'auto_trigger_key' => $triggerKey,
        ]);
    }
}
