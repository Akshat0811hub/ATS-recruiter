<?php

namespace App\Http\Controllers;

use App\Models\AtsNotification;
use App\Models\CandidateApplication;
use App\Models\Client;
use App\Models\ClientSubmission;
use App\Models\InterviewRecord;
use App\Models\InterviewRoundDefinition;
use App\Models\JobRequirement;
use App\Models\JoiningRecord;
use App\Models\Offer;
use App\Services\AuditService;
use App\Services\PipelineService;
use App\Services\StorageManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClientPortalController extends Controller
{
    public function __construct(
        private readonly StorageManagementService $storageService,
        private readonly PipelineService $pipelineService
    ) {}

    private function currentClient(): Client
    {
        $user = Auth::user();
        if (! $user || ! $user->client_id) {
            abort(403, 'Client Portal access requires an authorised client organisation account.');
        }

        return Client::findOrFail($user->client_id);
    }

    public function dashboard()
    {
        $client = $this->currentClient();

        $requirements = JobRequirement::where('client_id', $client->id)
            ->withCount(['submissions', 'interviews', 'offers', 'joinings'])
            ->orderByDesc('id')
            ->get();

        // Only expose client-safe fields; never expose internal_notes or recruiter margins
        $submissions = ClientSubmission::where('client_id', $client->id)
            ->with(['jobRequirement', 'resumeVersion'])
            ->orderByDesc('submitted_at')
            ->limit(25)
            ->get();

        $interviews = InterviewRecord::where('client_id', $client->id)
            ->with(['candidate', 'jobRequirement'])
            ->orderByDesc('interview_date')
            ->limit(20)
            ->get();

        $offers = Offer::where('client_id', $client->id)
            ->with(['candidate', 'jobRequirement'])
            ->orderByDesc('id')
            ->get();

        $joinings = JoiningRecord::where('client_id', $client->id)
            ->with(['candidate', 'jobRequirement'])
            ->orderByDesc('id')
            ->get();

        $stats = [
            'total_requirements' => $requirements->count(),
            'open_vacancies' => (int) $requirements->whereNotIn('status', ['successfully_closed', 'cancelled'])->sum('remaining_count'),
            'profiles_submitted' => ClientSubmission::where('client_id', $client->id)->count(),
            'pending_review' => ClientSubmission::where('client_id', $client->id)->where('client_decision', 'pending_review')->count(),
            'shortlisted' => ClientSubmission::where('client_id', $client->id)->whereIn('client_decision', ['shortlisted', 'interview_requested'])->count(),
            'interviews_scheduled' => InterviewRecord::where('client_id', $client->id)->count(),
            'candidates_joined' => JoiningRecord::where('client_id', $client->id)->whereIn('status', ['joined', 'placement_completed'])->count(),
        ];

        return view('portal.dashboard', compact('client', 'requirements', 'submissions', 'interviews', 'offers', 'joinings', 'stats'));
    }

    public function storeRequirement(Request $request)
    {
        $client = $this->currentClient();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'openings_count' => ['required', 'integer', 'min:1', 'max:500'],
            'department' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string'],
            'required_education' => ['nullable', 'string', 'max:255'],
            'required_skills_csv' => ['nullable', 'string'],
            'min_experience' => ['required', 'numeric', 'min:0'],
            'max_experience' => ['nullable', 'numeric', 'min:0', 'gte:min_experience'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'min:0', 'gte:min_salary'],
            'currency' => ['nullable', 'string', 'max:10'],
            'location' => ['required', 'string', 'max:180'],
            'work_mode' => ['required', 'in:onsite,hybrid,remote'],
            'employment_type' => ['nullable', 'string', 'max:40'],
            'priority' => ['nullable', 'in:critical,high,medium,low'],
            'expected_closure_date' => ['nullable', 'date'],
            'interview_rounds_count' => ['nullable', 'integer', 'min:1', 'max:15'],
            'client_instructions' => ['nullable', 'string'],
            'jd_file' => ['nullable', 'file', 'max:10240'],
        ]);

        return DB::transaction(function () use ($request, $client, $data) {
            $jdStorageId = null;
            if ($request->hasFile('jd_file')) {
                $rec = $this->storageService->storePrivateFile(
                    $request->file('jd_file'),
                    'jd',
                    JobRequirement::class
                );
                $jdStorageId = $rec->id;
            }

            $skills = ! empty($data['required_skills_csv'])
                ? array_values(array_filter(array_map('trim', explode(',', $data['required_skills_csv']))))
                : [];

            $roundsCount = (int) ($data['interview_rounds_count'] ?? 3);

            $job = JobRequirement::create([
                'client_id' => $client->id,
                'title' => $data['title'],
                'openings_count' => (int) $data['openings_count'],
                'joined_count' => 0,
                'remaining_count' => (int) $data['openings_count'],
                'department' => $data['department'] ?? null,
                'industry' => $client->industry,
                'description' => $data['description'],
                'jd_storage_id' => $jdStorageId,
                'required_education' => $data['required_education'] ?? null,
                'required_skills' => $skills,
                'min_experience' => $data['min_experience'],
                'max_experience' => $data['max_experience'] ?? null,
                'min_salary' => $data['min_salary'] ?? null,
                'max_salary' => $data['max_salary'] ?? null,
                'currency' => $data['currency'] ?? $client->currency ?? 'INR',
                'location' => $data['location'],
                'country' => $client->country ?? 'India',
                'work_mode' => $data['work_mode'],
                'employment_type' => $data['employment_type'] ?? 'full_time',
                'priority' => $data['priority'] ?? 'high',
                'expected_closure_date' => $data['expected_closure_date'] ?? null,
                'interview_rounds_count' => $roundsCount,
                'client_instructions' => $data['client_instructions'] ?? null,
                'status' => 'new_requirement',
                'assigned_team_id' => $client->assigned_team_id,
                'created_by' => Auth::id(),
            ]);

            for ($i = 1; $i <= $roundsCount; $i++) {
                InterviewRoundDefinition::create([
                    'job_requirement_id' => $job->id,
                    'round_number' => $i,
                    'round_name' => "Round {$i}: ".($i === 1 ? 'Initial Screening' : ($i === $roundsCount ? 'Final Management Discussion' : 'Technical Assessment')),
                    'interview_mode' => 'video',
                    'is_mandatory' => true,
                ]);
            }

            if ($client->account_manager_id) {
                AtsNotification::sendToUser(
                    $client->account_manager_id,
                    "New Client Requirement: {$job->title}",
                    "{$client->company_name} submitted requirement {$job->job_code} ({$job->openings_count} openings).",
                    'new_requirement',
                    "/jobs/{$job->id}"
                );
            }

            AuditService::log('portal.job_created', $job, $job->id, null, [
                'client_id' => $client->id,
                'job_code' => $job->job_code,
            ]);

            return redirect()->route('portal.dashboard')->with('success', "Requirement [{$job->title}] ({$job->job_code}) submitted to OrbiTouch HR.");
        });
    }

    public function compare(Request $request)
    {
        $client = $this->currentClient();
        $ids = array_map('intval', (array) $request->query('submission_ids', []));

        // Strictly isolated to current client's submissions
        $submissions = ClientSubmission::where('client_id', $client->id)
            ->when(! empty($ids), fn ($q) => $q->whereIn('id', $ids))
            ->with(['jobRequirement', 'resumeVersion', 'candidate.skills', 'candidateApplication.interviewRecords'])
            ->limit(5)
            ->get();

        return view('portal.compare', compact('client', 'submissions'));
    }

    public function confirmFinalSelection(Request $request, CandidateApplication $application)
    {
        $client = $this->currentClient();
        if ((int) $application->client_id !== (int) $client->id) {
            abort(403, 'Cross-client access denied.');
        }

        $data = $request->validate([
            'offered_designation' => ['required', 'string', 'max:160'],
            'offered_salary' => ['required', 'numeric', 'min:0'],
            'expected_joining_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($client, $application, $data) {
            $this->pipelineService->transitionStage(
                $application,
                'selected',
                $data['remarks'] ?? 'Confirmed final selection via Client Portal',
                null,
                true
            );

            $offer = Offer::updateOrCreate(
                ['candidate_application_id' => $application->id],
                [
                    'job_requirement_id' => $application->job_requirement_id,
                    'client_id' => $client->id,
                    'candidate_id' => $application->candidate_id,
                    'recruiter_id' => $application->recruiter_id,
                    'selection_date' => now()->toDateString(),
                    'offered_designation' => $data['offered_designation'],
                    'offered_salary' => $data['offered_salary'],
                    'negotiated_salary' => $data['offered_salary'],
                    'currency' => $client->currency ?? 'INR',
                    'expected_joining_date' => $data['expected_joining_date'],
                    'status' => 'selected',
                    'negotiation_notes' => $data['remarks'] ?? null,
                ]
            );

            AuditService::log('portal.selection_confirmed', $offer, $offer->id);

            return back()->with('success', "Final selection confirmed for {$application->candidate?->full_name} ({$offer->offer_code}).");
        });
    }
}
