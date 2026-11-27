<?php

namespace App\Services;

use App\Models\JoiningRecord;
use App\Models\Placement;
use App\Models\ReplacementRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VacancyClosureService
{
    public function __construct(private readonly PipelineService $pipelineService) {}

    /**
     * Confirm candidate joining, create idempotent Placement record, compute replacement expiry,
     * and atomically recalculate job remaining vacancy counts.
     */
    public function confirmJoining(
        JoiningRecord $joining,
        string $actualJoiningDate,
        float $finalSalary,
        bool $candidateConfirmed = true,
        bool $clientConfirmed = true,
        ?string $remarks = null
    ): JoiningRecord {
        return DB::transaction(function () use ($joining, $actualJoiningDate, $finalSalary, $candidateConfirmed, $clientConfirmed, $remarks) {
            $lockedJoining = JoiningRecord::lockForUpdate()->findOrFail($joining->id);
            $job = $lockedJoining->jobRequirement()->lockForUpdate()->firstOrFail();
            $client = $lockedJoining->client;

            $joinedDate = Carbon::parse($actualJoiningDate);
            $replacementDays = (int) ($lockedJoining->replacement_period_days ?: ($client?->replacement_period_days ?? 90));
            $expiryDate = $joinedDate->copy()->addDays($replacementDays);

            $feePercent = (float) ($client?->placement_fee_percentage ?? 8.33);
            $placementValue = $client?->flat_placement_fee
                ? (float) $client->flat_placement_fee
                : round(($finalSalary * $feePercent) / 100, 2);

            $lockedJoining->update([
                'actual_joining_date' => $joinedDate->toDateString(),
                'candidate_confirmed' => $candidateConfirmed,
                'client_confirmed' => $clientConfirmed,
                'status' => 'joined',
                'final_salary' => $finalSalary,
                'placement_value' => $placementValue,
                'replacement_period_days' => $replacementDays,
                'replacement_expiry_date' => $expiryDate->toDateString(),
                'next_checkin_date' => $joinedDate->copy()->addDays(30)->toDateString(),
                'vacancy_counted' => true,
                'remarks' => $remarks ?? $lockedJoining->remarks,
            ]);

            // Prevent duplicate placement counting using unique candidate_application_id
            Placement::updateOrCreate(
                ['candidate_application_id' => $lockedJoining->candidate_application_id],
                [
                    'joining_record_id' => $lockedJoining->id,
                    'job_requirement_id' => $lockedJoining->job_requirement_id,
                    'client_id' => $lockedJoining->client_id,
                    'candidate_id' => $lockedJoining->candidate_id,
                    'recruiter_id' => $lockedJoining->recruiter_id,
                    'joined_date' => $joinedDate->toDateString(),
                    'final_ctc' => $finalSalary,
                    'billable_fee' => $placementValue,
                    'currency' => $lockedJoining->currency ?? 'INR',
                    'warranty_end_date' => $expiryDate->toDateString(),
                    'status' => 'active_warranty',
                ]
            );

            $this->pipelineService->transitionStage(
                $lockedJoining->candidateApplication,
                'joined',
                $remarks ?? "Confirmed joining on {$joinedDate->toDateString()}",
                null,
                true
            );

            $job->recalculateVacancies();

            AuditService::log(
                'joining.confirmed',
                $lockedJoining,
                $lockedJoining->id,
                null,
                [
                    'actual_joining_date' => $joinedDate->toDateString(),
                    'final_salary' => $finalSalary,
                    'placement_value' => $placementValue,
                    'job_remaining_count' => $job->fresh()->remaining_count,
                ],
                $remarks
            );

            return $lockedJoining->fresh(['placement', 'jobRequirement']);
        });
    }

    /**
     * Handle a candidate who accepted an offer but did not join.
     */
    public function markDidNotJoin(JoiningRecord $joining, string $reason): JoiningRecord
    {
        return DB::transaction(function () use ($joining, $reason) {
            $lockedJoining = JoiningRecord::lockForUpdate()->findOrFail($joining->id);
            $job = $lockedJoining->jobRequirement()->lockForUpdate()->firstOrFail();

            $lockedJoining->update([
                'status' => 'did_not_join',
                'vacancy_counted' => false,
                'non_joining_reason' => $reason,
            ]);

            Placement::where('joining_record_id', $lockedJoining->id)->delete();

            $this->pipelineService->transitionStage(
                $lockedJoining->candidateApplication,
                'did_not_join',
                $reason,
                null,
                true
            );

            $job->recalculateVacancies();

            AuditService::log(
                'joining.did_not_join',
                $lockedJoining,
                $lockedJoining->id,
                null,
                ['status' => 'did_not_join', 'reason' => $reason],
                $reason
            );

            return $lockedJoining->fresh();
        });
    }

    /**
     * Trigger a replacement obligation when a joined candidate leaves during the replacement window
     * and reopen the vacancy on the job requirement when authorised.
     */
    public function triggerReplacementAndReopenVacancy(
        JoiningRecord $joining,
        string $exitDate,
        string $exitReason,
        bool $reopenVacancy = true
    ): ReplacementRecord {
        return DB::transaction(function () use ($joining, $exitDate, $exitReason, $reopenVacancy) {
            $lockedJoining = JoiningRecord::lockForUpdate()->findOrFail($joining->id);
            $job = $lockedJoining->jobRequirement()->lockForUpdate()->firstOrFail();
            $placement = $lockedJoining->placement;

            if (! $placement && ! in_array($lockedJoining->status, ['joined', 'placement_completed', 'left_after_joining', 'replacement_required'], true)) {
                throw new \RuntimeException('Replacement can only be opened after a candidate joining has been confirmed.');
            }

            $existingOpenReplacement = ReplacementRecord::where('joining_record_id', $lockedJoining->id)
                ->whereIn('status', ['open', 'in_progress'])
                ->first();

            if ($existingOpenReplacement) {
                return $existingOpenReplacement;
            }

            $parsedExit = Carbon::parse($exitDate);
            $withinPeriod = $lockedJoining->replacement_expiry_date
                ? $parsedExit->lte($lockedJoining->replacement_expiry_date)
                : true;

            if (! $placement) {
                $joinedAt = $lockedJoining->actual_joining_date ?? $lockedJoining->expected_joining_date ?? now();
                $placement = Placement::create([
                    'joining_record_id' => $lockedJoining->id,
                    'candidate_application_id' => $lockedJoining->candidate_application_id,
                    'job_requirement_id' => $job->id,
                    'client_id' => $lockedJoining->client_id,
                    'candidate_id' => $lockedJoining->candidate_id,
                    'recruiter_id' => $lockedJoining->recruiter_id,
                    'joined_date' => $joinedAt->toDateString(),
                    'final_ctc' => (float) ($lockedJoining->final_salary ?? 0),
                    'billable_fee' => (float) ($lockedJoining->placement_value ?? 0),
                    'currency' => $lockedJoining->currency ?? 'INR',
                    'warranty_end_date' => ($lockedJoining->replacement_expiry_date ?? $joinedAt->copy()->addDays(90))->toDateString(),
                    'status' => 'replacement_triggered',
                ]);
            } else {
                $placement->update(['status' => 'replacement_triggered']);
            }

            $lockedJoining->update([
                'status' => $reopenVacancy ? 'replacement_required' : 'left_after_joining',
                'vacancy_counted' => false,
                'remarks' => trim($lockedJoining->remarks."\nLeft after joining ({$parsedExit->toDateString()}): {$exitReason}"),
            ]);

            $replacement = ReplacementRecord::create([
                'placement_id' => $placement->id,
                'joining_record_id' => $lockedJoining->id,
                'job_requirement_id' => $job->id,
                'client_id' => $lockedJoining->client_id,
                'original_candidate_id' => $lockedJoining->candidate_id,
                'exit_date' => $parsedExit->toDateString(),
                'exit_reason' => $exitReason,
                'within_replacement_period' => $withinPeriod,
                'vacancy_reopened' => $reopenVacancy,
                'status' => 'open',
                'authorised_by' => Auth::id(),
                'remarks' => $exitReason,
            ]);

            // Recalculate job vacancies so the exited candidate is no longer counted as a filled opening
            $job->recalculateVacancies();

            AuditService::log(
                'replacement.opened',
                $replacement,
                $replacement->id,
                ['job_status' => $job->status],
                [
                    'exit_date' => $parsedExit->toDateString(),
                    'within_replacement_period' => $withinPeriod,
                    'vacancy_reopened' => $reopenVacancy,
                    'job_remaining_count' => $job->fresh()->remaining_count,
                ],
                $exitReason
            );

            return $replacement;
        });
    }
}
