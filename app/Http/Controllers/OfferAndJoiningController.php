<?php

namespace App\Http\Controllers;

use App\Models\AtsNotification;
use App\Models\CandidateApplication;
use App\Models\Client;
use App\Models\JoiningRecord;
use App\Models\Offer;
use App\Models\OfferRevision;
use App\Models\ReplacementRecord;
use App\Services\AuditService;
use App\Services\EmailService;
use App\Services\PipelineService;
use App\Services\RecruitmentAccess;
use App\Services\StorageManagementService;
use App\Services\VacancyClosureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OfferAndJoiningController extends Controller
{
    public function __construct(
        private readonly PipelineService $pipelineService,
        private readonly StorageManagementService $storageService,
        private readonly VacancyClosureService $vacancyClosureService,
        private readonly EmailService $emailService
    ) {}

    public function offers(Request $request)
    {
        $query = RecruitmentAccess::query(Offer::class)->with(['candidate', 'jobRequirement', 'client', 'recruiter', 'offerLetterStorage', 'revisions']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($clientId = $request->query('client_id')) {
            $query->where('client_id', $clientId);
        }

        $offers = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $clients = RecruitmentAccess::query(Client::class)->orderBy('company_name')->get();
        $selectedApplications = RecruitmentAccess::query(CandidateApplication::class)->with(['candidate', 'jobRequirement.client'])
            ->whereIn('pipeline_stage', ['interview_in_progress', 'selected', 'offer_released', 'offer_accepted'])
            ->orderByDesc('id')
            ->get();

        return view('offers.index', compact('offers', 'clients', 'selectedApplications'));
    }

    public function storeOrUpdateOffer(Request $request, CandidateApplication $application)
    {
        $data = $request->validate([
            'selection_date' => ['required', 'date'],
            'offered_designation' => ['required', 'string', 'max:160'],
            'offered_salary' => ['required', 'numeric', 'min:0'],
            'negotiated_salary' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'offer_release_date' => ['nullable', 'date'],
            'expected_joining_date' => ['nullable', 'date'],
            'candidate_acceptance_date' => ['nullable', 'date'],
            'status' => ['required', 'in:'.implode(',', array_keys(Offer::STATUSES))],
            'negotiation_notes' => ['nullable', 'string', 'max:3000'],
            'decline_or_backout_reason' => ['nullable', 'string', 'max:255'],
            'next_followup_date' => ['nullable', 'date'],
            'offer_letter_file' => ['nullable', 'file', 'max:10240'],
        ]);

        return DB::transaction(function () use ($request, $application, $data) {
            $offerLetterStorageId = null;
            if ($request->hasFile('offer_letter_file')) {
                $rec = $this->storageService->storePrivateFile(
                    $request->file('offer_letter_file'),
                    'offer_letter',
                    CandidateApplication::class,
                    $application->id
                );
                $offerLetterStorageId = $rec->id;
            }

            $existingOffer = Offer::where('candidate_application_id', $application->id)->lockForUpdate()->first();
            $salaryChanged = $existingOffer && (
                (float) $existingOffer->offered_salary !== (float) $data['offered_salary']
                || (float) ($existingOffer->negotiated_salary ?? 0) !== (float) ($data['negotiated_salary'] ?? 0)
            );

            $offer = Offer::updateOrCreate(
                ['candidate_application_id' => $application->id],
                [
                    'job_requirement_id' => $application->job_requirement_id,
                    'client_id' => $application->client_id,
                    'candidate_id' => $application->candidate_id,
                    'recruiter_id' => $application->recruiter_id ?? Auth::id(),
                    'selection_date' => $data['selection_date'],
                    'offered_designation' => $data['offered_designation'],
                    'offered_salary' => $data['offered_salary'],
                    'negotiated_salary' => $data['negotiated_salary'] ?? $data['offered_salary'],
                    'currency' => $data['currency'],
                    'offer_release_date' => $data['offer_release_date'] ?? ($existingOffer?->offer_release_date ?? now()->toDateString()),
                    'offer_letter_storage_id' => $offerLetterStorageId ?? $existingOffer?->offer_letter_storage_id,
                    'expected_joining_date' => $data['expected_joining_date'] ?? null,
                    'candidate_acceptance_date' => $data['candidate_acceptance_date'] ?? ($data['status'] === 'offer_accepted' ? now()->toDateString() : null),
                    'status' => $data['status'],
                    'negotiation_notes' => $data['negotiation_notes'] ?? null,
                    'decline_or_backout_reason' => $data['decline_or_backout_reason'] ?? null,
                    'next_followup_date' => $data['next_followup_date'] ?? now()->addDays(2)->toDateString(),
                ]
            );

            if (! $existingOffer || $salaryChanged) {
                $revNum = ((int) $offer->revisions()->max('revision_number')) + 1;
                OfferRevision::create([
                    'offer_id' => $offer->id,
                    'revision_number' => $revNum,
                    'offered_designation' => $offer->offered_designation,
                    'offered_salary' => $offer->negotiated_salary ?? $offer->offered_salary,
                    'currency' => $offer->currency,
                    'expected_joining_date' => $offer->expected_joining_date,
                    'revision_reason' => $data['negotiation_notes'] ?? ($existingOffer ? 'Salary / offer revision' : 'Initial offer generated'),
                    'revised_by' => Auth::id(),
                ]);
            }

            $pipelineStage = match ($data['status']) {
                'selected', 'offer_pending' => 'selected',
                'offer_released', 'under_negotiation' => 'offer_released',
                'offer_accepted' => 'offer_accepted',
                'offer_rejected', 'candidate_backed_out', 'offer_withdrawn' => 'dropped_out',
                default => 'offer_released',
            };

            $this->pipelineService->transitionStage(
                $application,
                $pipelineStage,
                $data['decline_or_backout_reason'] ?? $data['negotiation_notes'] ?? "Offer status: {$data['status']}"
            );

            // When offer is accepted, automatically initialize a JoiningRecord if not present
            if ($data['status'] === 'offer_accepted') {
                JoiningRecord::firstOrCreate(
                    ['candidate_application_id' => $application->id],
                    [
                        'offer_id' => $offer->id,
                        'job_requirement_id' => $application->job_requirement_id,
                        'client_id' => $application->client_id,
                        'candidate_id' => $application->candidate_id,
                        'recruiter_id' => $offer->recruiter_id,
                        'expected_joining_date' => $offer->expected_joining_date ?? now()->addDays(15)->toDateString(),
                        'status' => 'joining_pending',
                        'final_salary' => $offer->negotiated_salary ?? $offer->offered_salary,
                        'currency' => $offer->currency,
                        'replacement_period_days' => $application->client?->replacement_period_days ?? 90,
                    ]
                );
            }

            if ($application->jobRequirement && in_array($application->jobRequirement->status, ['sourcing_in_progress', 'interview_in_progress'], true)) {
                $application->jobRequirement->update(['status' => 'offer_in_progress']);
            }

            AuditService::log('offer.saved', $offer, $offer->id, null, [
                'offer_code' => $offer->offer_code,
                'status' => $offer->status,
                'salary' => $offer->negotiated_salary ?? $offer->offered_salary,
            ]);

            return back()->with('success', "Offer record [{$offer->offer_code}] saved ({$offer->status}).");
        });
    }

    public function joinings(Request $request)
    {
        $query = RecruitmentAccess::query(JoiningRecord::class)->with([
            'candidate',
            'jobRequirement',
            'client',
            'recruiter',
            'offer',
            'placement',
            'replacementRecords',
        ]);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($clientId = $request->query('client_id')) {
            $query->where('client_id', $clientId);
        }

        $joinings = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $replacements = RecruitmentAccess::query(ReplacementRecord::class)->with(['client', 'jobRequirement', 'originalCandidate', 'authoriser'])->latest()->limit(15)->get();
        $clients = RecruitmentAccess::query(Client::class)->orderBy('company_name')->get();

        return view('joinings.index', compact('joinings', 'replacements', 'clients'));
    }

    public function confirmJoining(Request $request, JoiningRecord $joining)
    {
        $user = Auth::user();
        if ($user->isClient() && (int) $joining->client_id !== (int) $user->client_id) {
            abort(403, 'Unauthorised client organisation.');
        }

        $data = $request->validate([
            'actual_joining_date' => ['required', 'date'],
            'final_salary' => ['nullable', 'numeric', 'min:0'],
            'candidate_confirmed' => ['nullable', 'boolean'],
            'client_confirmed' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'joining_document_file' => ['nullable', 'file', 'max:10240'],
        ]);

        if ($request->hasFile('joining_document_file')) {
            $doc = $this->storageService->storePrivateFile(
                $request->file('joining_document_file'),
                'joining_doc',
                JoiningRecord::class,
                $joining->id
            );
            $joining->update(['joining_document_storage_id' => $doc->id]);
        }

        $finalSalary = (float) ($data['final_salary'] ?? $joining->final_salary ?? $joining->offer?->negotiated_salary ?? $joining->offer?->offered_salary ?? 0);

        $updated = $this->vacancyClosureService->confirmJoining(
            $joining,
            $data['actual_joining_date'],
            $finalSalary,
            $request->boolean('candidate_confirmed', true),
            $request->boolean('client_confirmed', true),
            $data['remarks'] ?? null
        );

        if ($updated->client?->primary_hr_email) {
            $this->emailService->sendTemplatedEmail(
                'joining_confirmation',
                $updated->client->primary_hr_email,
                $updated->client->primary_hr_name,
                [
                    'client_name' => $updated->client->primary_hr_name,
                    'candidate_name' => $updated->candidate?->full_name ?? '',
                    'job_title' => $updated->jobRequirement?->title ?? '',
                    'actual_joining_date' => $updated->actual_joining_date?->toDateString() ?? '',
                ],
                $updated
            );
        }

        return back()->with('success', "Confirmed joining for {$updated->candidate?->full_name}. Job [{$updated->jobRequirement?->job_code}] now has {$updated->jobRequirement?->remaining_count} remaining vacancy(ies).");
    }

    public function markDidNotJoin(Request $request, JoiningRecord $joining)
    {
        $data = $request->validate([
            'non_joining_reason' => ['required', 'string', 'max:255'],
        ]);

        $this->vacancyClosureService->markDidNotJoin($joining, $data['non_joining_reason']);

        return back()->with('success', "Recorded Did Not Join for {$joining->candidate?->full_name}. Vacancy counts recalculated.");
    }

    public function recordCheckin(Request $request, JoiningRecord $joining)
    {
        $data = $request->validate([
            'checkin_interval' => ['required', 'string', 'max:40'], // 15-Day, 30-Day, 60-Day, 90-Day
            'checkin_notes' => ['required', 'string', 'max:2000'],
            'mark_placement_completed' => ['nullable', 'boolean'],
        ]);

        $checkins = $joining->post_joining_checkins ?? [];
        $checkins[] = [
            'interval' => $data['checkin_interval'],
            'notes' => $data['checkin_notes'],
            'recorded_by' => Auth::user()?->name ?? 'System',
            'recorded_at' => now()->toDateTimeString(),
        ];

        $newStatus = $request->boolean('mark_placement_completed') ? 'placement_completed' : $joining->status;

        $joining->update([
            'post_joining_checkins' => $checkins,
            'status' => $newStatus,
            'next_checkin_date' => $newStatus === 'placement_completed' ? null : now()->addDays(30)->toDateString(),
        ]);

        if ($newStatus === 'placement_completed' && $joining->placement) {
            $joining->placement->update(['status' => 'placement_completed']);
        }

        AuditService::log('joining.checkin_recorded', $joining, $joining->id, null, [
            'interval' => $data['checkin_interval'],
            'status' => $newStatus,
        ]);

        return back()->with('success', "Post-joining check-in ({$data['checkin_interval']}) recorded.");
    }

    public function triggerReplacement(Request $request, JoiningRecord $joining)
    {
        $data = $request->validate([
            'exit_date' => ['required', 'date'],
            'exit_reason' => ['required', 'string', 'max:255'],
            'reopen_vacancy' => ['nullable', 'boolean'],
        ]);

        $replacement = $this->vacancyClosureService->triggerReplacementAndReopenVacancy(
            $joining,
            $data['exit_date'],
            $data['exit_reason'],
            $request->boolean('reopen_vacancy', true)
        );

        if ($joining->recruiter_id) {
            AtsNotification::sendToUser(
                $joining->recruiter_id,
                'Replacement Vacancy Opened',
                "Candidate {$joining->candidate?->full_name} exited within replacement window. Job {$joining->jobRequirement?->job_code} vacancy reopened.",
                'replacement_alert',
                "/jobs/{$joining->job_requirement_id}"
            );
        }

        return back()->with('success', "Replacement record opened. Job [{$joining->jobRequirement?->job_code}] remaining vacancies updated to {$joining->jobRequirement?->fresh()->remaining_count}.");
    }
}
