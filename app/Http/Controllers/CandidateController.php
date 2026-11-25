<?php

namespace App\Http\Controllers;

use App\Models\CallLog;
use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\CandidateNote;
use App\Models\CandidateSkill;
use App\Models\JobRequirement;
use App\Models\ResumeVersion;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CandidateDuplicateService;
use App\Services\ExportImportService;
use App\Services\RecruitmentAccess;
use App\Services\StorageManagementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CandidateController extends Controller
{
    public function __construct(
        private readonly CandidateDuplicateService $duplicateService,
        private readonly StorageManagementService $storageService,
        private readonly ExportImportService $exportImportService
    ) {}

    private function filteredCandidatesQuery(Request $request): Builder
    {
        $query = RecruitmentAccess::query(Candidate::class);

        if ($search = trim((string) $request->query('search', ''))) {
            $normPhone = Candidate::normalizePhone($search);
            $query->where(function (Builder $q) use ($search, $normPhone) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('candidate_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile_number', 'like', "%{$search}%")
                    ->orWhere('current_employer', 'like', "%{$search}%")
                    ->orWhere('current_designation', 'like', "%{$search}%")
                    ->orWhereHas('skills', fn ($sq) => $sq->where('skill_name', 'like', "%{$search}%"));
                if ($normPhone && strlen($normPhone) >= 5) {
                    $q->orWhere('normalized_mobile', 'like', "%{$normPhone}%");
                }
            });
        }
        if ($location = trim((string) $request->query('location', ''))) {
            $query->where('current_location', 'like', "%{$location}%");
        }
        if ($recruiterId = $request->query('recruiter_id')) {
            $query->where('assigned_recruiter_id', (int) $recruiterId);
        }
        if ($stage = $request->query('stage')) {
            $query->where('current_stage', $stage);
        }
        if ($request->filled('max_notice_days')) {
            $query->whereNotNull('notice_period_days')
                ->where('notice_period_days', '<=', (int) $request->query('max_notice_days'));
        }

        return $query;
    }

    public function index(Request $request)
    {
        $candidates = $this->filteredCandidatesQuery($request)
            ->with(['assignedRecruiter', 'skills', 'primaryResume.storageRecord', 'applications' => fn ($q) => RecruitmentAccess::scope($q)->with('jobRequirement')])
            ->withCount(['applications', 'callLogs', 'clientSubmissions'])
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $recruiters = RecruitmentAccess::query(User::class)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['recruiter', 'team_leader', 'manager']))
            ->where('is_active', true)
            ->get();
        $activeJobs = RecruitmentAccess::query(JobRequirement::class)
            ->with('client')
            ->whereNotIn('status', ['successfully_closed', 'cancelled'])
            ->orderByDesc('id')
            ->get();
        $sources = SystemSetting::getValue('candidate_sources', ['Direct Sourcing', 'Naukri', 'LinkedIn Recruiter', 'Employee Referral']);

        return view('candidates.index', compact('candidates', 'recruiters', 'activeJobs', 'sources'));
    }

    public function checkDuplicates(Request $request)
    {
        $duplicates = $this->duplicateService->findDuplicates(
            $request->input('mobile_number'),
            $request->input('email'),
            $request->input('alternate_number'),
            $request->input('resume_checksum'),
            $request->input('exclude_id') ? (int) $request->input('exclude_id') : null
        )->filter(fn (Candidate $candidate) => RecruitmentAccess::query(Candidate::class)->whereKey($candidate->id)->exists())->values();

        return response()->json([
            'has_duplicates' => $duplicates->isNotEmpty(),
            'count' => $duplicates->count(),
            'duplicates' => $duplicates->map(fn (Candidate $c) => [
                'id' => $c->id,
                'candidate_code' => $c->candidate_code,
                'full_name' => $c->full_name,
                'mobile_number' => $c->mobile_number,
                'email' => $c->email,
                'current_employer' => $c->current_employer,
                'recruiter' => $c->assignedRecruiter?->name,
                'reasons' => $c->getAttribute('duplicate_reasons') ?? [],
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:180'],
            'mobile_number' => ['required', 'string', 'min:7', 'max:40'],
            'alternate_number' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'],
            'current_location' => ['nullable', 'string', 'max:150'],
            'preferred_location' => ['nullable', 'string', 'max:180'],
            'country' => ['nullable', 'string', 'max:100'],
            'current_employer' => ['nullable', 'string', 'max:180'],
            'current_designation' => ['nullable', 'string', 'max:160'],
            'total_experience' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'relevant_experience' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'qualifications' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:120'],
            'current_ctc' => ['nullable', 'numeric', 'min:0'],
            'expected_ctc' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_serving_notice' => ['nullable', 'boolean'],
            'last_working_date' => ['nullable', 'date'],
            'reason_for_change' => ['nullable', 'string', 'max:255'],
            'employment_preference' => ['nullable', 'string', 'max:40'],
            'work_mode_preference' => ['nullable', 'string', 'max:40'],
            'source' => ['nullable', 'string', 'max:80'],
            'skills_csv' => ['nullable', 'string'],
            'tags_csv' => ['nullable', 'string'],
            'consent_status' => ['nullable', 'in:granted,pending,withdrawn'],
            'next_follow_up_at' => ['nullable', 'date'],
            'availability_status' => ['nullable', 'string', 'max:60'],
            'remarks' => ['nullable', 'string'],
            'resume_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,rtf,txt', 'max:10240'],
            'job_requirement_id' => ['nullable', 'exists:job_requirements,id'],
            'confirm_duplicate_override' => ['nullable', 'boolean'],
        ], [
            'full_name.required' => 'Enter the candidate\'s full name.',
            'mobile_number.required' => 'Enter a mobile number so recruiters can reach the candidate.',
            'mobile_number.min' => 'Enter a valid mobile number with at least 7 digits.',
            'email.email' => 'Enter a valid email address (for example, name@company.com).',
            'total_experience.min' => 'Total experience cannot be negative.',
            'relevant_experience.min' => 'Relevant experience cannot be negative.',
            'notice_period_days.min' => 'Notice period days cannot be negative.',
            'current_ctc.min' => 'Current annual CTC cannot be negative.',
            'expected_ctc.min' => 'Expected annual CTC cannot be negative.',
            'resume_file.mimes' => 'Upload the resume as a PDF, DOC, DOCX, RTF, or TXT file.',
            'resume_file.max' => 'The resume file must be 10 MB or smaller.',
            'job_requirement_id.exists' => 'Select an active job requirement from your workspace.',
        ]);

        $resumeChecksum = null;
        if ($request->hasFile('resume_file') && $request->file('resume_file')->isValid()) {
            $realPath = $request->file('resume_file')->getRealPath();
            $resumeChecksum = $realPath && file_exists($realPath)
                ? hash_file('sha256', $realPath)
                : hash('sha256', $request->file('resume_file')->get());
        }

        // Check for duplicate candidates before saving unless explicitly overridden
        $duplicates = $this->duplicateService->findDuplicates(
            $data['mobile_number'],
            $data['email'] ?? null,
            $data['alternate_number'] ?? null,
            $resumeChecksum
        );

        if ($request->boolean('confirm_duplicate_override')) {
            abort_unless(Auth::user()->hasPermission('candidates.merge'), 403, 'Ask an administrator to review duplicate records.');
        }

        if ($duplicates->isNotEmpty() && ! $request->boolean('confirm_duplicate_override')) {
            return back()
                ->withInput()
                ->with('duplicate_warning', 'A matching candidate already exists. Review the existing profile or ask your manager before adding another record.');
        }

        return DB::transaction(function () use ($request, $data, $resumeChecksum) {
            $tags = ! empty($data['tags_csv'])
                ? array_values(array_filter(array_map('trim', explode(',', $data['tags_csv']))))
                : [];

            $consentStatus = $data['consent_status'] ?? 'pending';
            $totalExp = isset($data['total_experience']) && $data['total_experience'] !== null ? (float) $data['total_experience'] : 0.0;
            $relevantExp = isset($data['relevant_experience']) && $data['relevant_experience'] !== null ? (float) $data['relevant_experience'] : $totalExp;

            $candidate = Candidate::create([
                'full_name' => $data['full_name'],
                'mobile_number' => $data['mobile_number'],
                'alternate_number' => $data['alternate_number'] ?? null,
                'email' => $data['email'] ?? null,
                'current_location' => $data['current_location'] ?? null,
                'preferred_location' => $data['preferred_location'] ?? null,
                'country' => $data['country'] ?? 'India',
                'current_employer' => $data['current_employer'] ?? null,
                'current_designation' => $data['current_designation'] ?? null,
                'total_experience' => $totalExp,
                'relevant_experience' => $relevantExp,
                'qualifications' => $data['qualifications'] ?? null,
                'industry' => $data['industry'] ?? null,
                'current_ctc' => $data['current_ctc'] ?? null,
                'expected_ctc' => $data['expected_ctc'] ?? null,
                'currency' => $data['currency'] ?? 'INR',
                'notice_period_days' => array_key_exists('notice_period_days', $data) && $data['notice_period_days'] !== null ? (int) $data['notice_period_days'] : null,
                'is_serving_notice' => $request->boolean('is_serving_notice'),
                'last_working_date' => $data['last_working_date'] ?? null,
                'reason_for_change' => $data['reason_for_change'] ?? null,
                'employment_preference' => $data['employment_preference'] ?? 'full_time',
                'work_mode_preference' => $data['work_mode_preference'] ?? 'any',
                'source' => $data['source'] ?? 'Direct Sourcing',
                'assigned_recruiter_id' => Auth::id(),
                'created_by' => Auth::id(),
                'tags' => $tags,
                'primary_resume_checksum' => $resumeChecksum,
                'consent_status' => $consentStatus,
                'consent_recorded_at' => $consentStatus === 'granted' ? now() : null,
                'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
                'availability_status' => $data['availability_status'] ?? 'Active / Exploring',
                'remarks' => $data['remarks'] ?? null,
            ]);

            if (! empty($data['skills_csv'])) {
                $skills = array_unique(array_filter(array_map('trim', explode(',', $data['skills_csv']))));
                foreach ($skills as $sk) {
                    CandidateSkill::create([
                        'candidate_id' => $candidate->id,
                        'skill_name' => $sk,
                        'experience_years' => $candidate->relevant_experience,
                    ]);
                }
            }

            if ($request->hasFile('resume_file')) {
                $storageRecord = $this->storageService->storePrivateFile(
                    $request->file('resume_file'),
                    'resume',
                    Candidate::class,
                    $candidate->id
                );

                ResumeVersion::create([
                    'candidate_id' => $candidate->id,
                    'version_number' => 1,
                    'storage_record_id' => $storageRecord->id,
                    'original_filename' => $storageRecord->original_name,
                    'mime_type' => $storageRecord->mime_type,
                    'file_size_bytes' => $storageRecord->size_bytes,
                    'sha256_checksum' => $storageRecord->sha256_checksum,
                    'is_primary' => true,
                    'uploaded_by' => Auth::id(),
                    'notes' => 'Initial resume upload',
                ]);

                $candidate->update(['primary_resume_checksum' => $storageRecord->sha256_checksum]);
            }

            if (! empty($data['job_requirement_id'])) {
                $job = RecruitmentAccess::query(JobRequirement::class)->findOrFail($data['job_requirement_id']);
                CandidateApplication::firstOrCreate(
                    [
                        'candidate_id' => $candidate->id,
                        'job_requirement_id' => $job->id,
                    ],
                    [
                        'client_id' => $job->client_id,
                        'recruiter_id' => Auth::id(),
                        'pipeline_stage' => 'sourced',
                        'applied_ctc' => $candidate->current_ctc,
                        'expected_ctc' => $candidate->expected_ctc,
                        'currency' => $candidate->currency,
                        'notice_period_days' => $candidate->notice_period_days,
                    ]
                );
            }

            AuditService::log('candidate.created', $candidate, $candidate->id, null, [
                'candidate_code' => $candidate->candidate_code,
                'full_name' => $candidate->full_name,
            ]);

            AuditService::recordActivity(
                $candidate,
                'candidate_created',
                "Master candidate profile {$candidate->candidate_code} created by ".(Auth::user()?->name ?? 'System')
            );

            return redirect()->route('candidates.show', $candidate)
                ->with('success', "Candidate [{$candidate->full_name}] ({$candidate->candidate_code}) added to master database.");
        });
    }

    public function show(Candidate $candidate)
    {
        $candidate->load([
            'assignedRecruiter',
            'skills',
            'resumeVersions.storageRecord',
            'resumeVersions.uploader',
            'documents.storageRecord',
            'notes.user',
            'applications' => fn ($q) => RecruitmentAccess::scope($q)->with(['jobRequirement.client', 'recruiter', 'latestScreening']),
            'callLogs' => fn ($q) => RecruitmentAccess::scope($q)->with(['recruiter', 'jobRequirement']),
            'clientSubmissions' => fn ($q) => RecruitmentAccess::scope($q)->with(['jobRequirement', 'client']),
            'interviewRecords' => fn ($q) => RecruitmentAccess::scope($q)->with('jobRequirement'),
            'offers' => fn ($q) => RecruitmentAccess::scope($q)->with('jobRequirement'),
            'joiningRecords' => fn ($q) => RecruitmentAccess::scope($q)->with('jobRequirement'),
            'tasks' => fn ($q) => RecruitmentAccess::scope($q)->with('assignee'),
            'activityLogs.user',
        ]);

        $potentialDuplicates = $this->duplicateService->findDuplicates(
            $candidate->mobile_number,
            $candidate->email,
            $candidate->alternate_number,
            $candidate->primary_resume_checksum,
            $candidate->id
        )->filter(fn (Candidate $duplicate) => RecruitmentAccess::query(Candidate::class)->whereKey($duplicate->id)->exists());

        $activeJobs = RecruitmentAccess::query(JobRequirement::class)->with('client')
            ->whereNotIn('status', ['successfully_closed', 'cancelled'])
            ->orderByDesc('id')
            ->get();

        return view('candidates.show', compact('candidate', 'potentialDuplicates', 'activeJobs'));
    }

    public function preview(Candidate $candidate)
    {
        $candidate->load([
            'assignedRecruiter',
            'skills',
            'primaryResume',
            'applications' => fn ($q) => RecruitmentAccess::scope($q)->with('jobRequirement.client'),
            'callLogs' => fn ($q) => RecruitmentAccess::scope($q)->limit(3),
        ]);

        return response()->json([
            'id' => $candidate->id,
            'profile_url' => route('candidates.show', $candidate),
            'candidate_code' => $candidate->candidate_code,
            'full_name' => $candidate->full_name,
            'mobile_number' => $candidate->mobile_number,
            'email' => $candidate->email,
            'current_location' => $candidate->current_location,
            'current_employer' => $candidate->current_employer,
            'current_designation' => $candidate->current_designation,
            'total_experience' => $candidate->total_experience,
            'relevant_experience' => $candidate->relevant_experience,
            'current_ctc' => $candidate->current_ctc,
            'expected_ctc' => $candidate->expected_ctc,
            'currency' => $candidate->currency,
            'notice_period_days' => $candidate->notice_period_days,
            'current_stage' => $candidate->current_stage,
            'recruiter' => $candidate->assignedRecruiter?->name,
            'skills' => $candidate->skills->pluck('skill_name'),
            'has_resume' => $candidate->primaryResume !== null,
            'resume_download_url' => $candidate->primaryResume ? route('documents.download', $candidate->primaryResume->storage_record_id) : null,
            'applications' => $candidate->applications->map(fn ($app) => [
                'id' => $app->id,
                'job_code' => $app->jobRequirement?->job_code,
                'job_title' => $app->jobRequirement?->title,
                'client_name' => $app->jobRequirement?->client?->company_name,
                'stage' => $app->stageLabel(),
                'recruiter_id' => $app->recruiter_id,
            ]),
            'recent_calls' => $candidate->callLogs->map(fn ($call) => [
                'called_at' => $call->called_at?->format('d M Y, H:i'),
                'outcome' => CallLog::OUTCOMES[$call->outcome] ?? $call->outcome,
                'summary' => $call->discussion_summary,
            ]),
        ]);
    }

    public function update(Request $request, Candidate $candidate)
    {
        $user = Auth::user();
        if ($user->isRecruiter() && $candidate->assigned_recruiter_id && (int) $candidate->assigned_recruiter_id !== (int) $user->id) {
            $worksOnSameCandidate = RecruitmentAccess::query(CandidateApplication::class)
                ->where('candidate_id', $candidate->id)
                ->exists();
            if (! $worksOnSameCandidate) {
                abort(403, 'Unauthorised: This candidate profile is owned by another recruiter.');
            }
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:180'],
            'mobile_number' => ['required', 'string', 'min:7', 'max:40'],
            'alternate_number' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'],
            'current_location' => ['nullable', 'string', 'max:150'],
            'preferred_location' => ['nullable', 'string', 'max:180'],
            'current_employer' => ['nullable', 'string', 'max:180'],
            'current_designation' => ['nullable', 'string', 'max:160'],
            'total_experience' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'relevant_experience' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'qualifications' => ['nullable', 'string', 'max:255'],
            'current_ctc' => ['nullable', 'numeric', 'min:0'],
            'expected_ctc' => ['nullable', 'numeric', 'min:0'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_serving_notice' => ['nullable', 'boolean'],
            'consent_status' => ['nullable', 'in:granted,pending,withdrawn,erasure_requested'],
            'availability_status' => ['nullable', 'string', 'max:60'],
            'remarks' => ['nullable', 'string'],
            'skills_csv' => ['nullable', 'string'],
        ], [
            'full_name.required' => 'Enter the candidate\'s full name.',
            'mobile_number.required' => 'Enter a mobile number for the candidate.',
            'email.email' => 'Enter a valid email address.',
        ]);

        $old = $candidate->only(['full_name', 'mobile_number', 'email', 'current_ctc', 'expected_ctc', 'notice_period_days', 'consent_status']);

        $payload = collect($data)->except('skills_csv')->toArray();
        if (array_key_exists('total_experience', $payload) && $payload['total_experience'] === null) {
            $payload['total_experience'] = 0.0;
        }
        if (array_key_exists('relevant_experience', $payload) && $payload['relevant_experience'] === null) {
            $payload['relevant_experience'] = $payload['total_experience'] ?? $candidate->total_experience ?? 0.0;
        }
        if (array_key_exists('consent_status', $payload) && $payload['consent_status'] === null) {
            $payload['consent_status'] = $candidate->consent_status ?: 'pending';
        }
        if (array_key_exists('availability_status', $payload) && $payload['availability_status'] === null) {
            $payload['availability_status'] = $candidate->availability_status ?: 'Active / Exploring';
        }
        if ($request->has('is_serving_notice')) {
            $payload['is_serving_notice'] = $request->boolean('is_serving_notice');
        }

        $candidate->update($payload);

        if (array_key_exists('skills_csv', $data)) {
            $skills = array_unique(array_filter(array_map('trim', explode(',', (string) $data['skills_csv']))));
            $candidate->skills()->delete();
            foreach ($skills as $sk) {
                CandidateSkill::create([
                    'candidate_id' => $candidate->id,
                    'skill_name' => $sk,
                    'experience_years' => $candidate->relevant_experience,
                ]);
            }
        }

        AuditService::log('candidate.updated', $candidate, $candidate->id, $old, $candidate->only(array_keys($old)));
        AuditService::recordActivity($candidate, 'candidate_updated', 'Candidate master profile updated');

        return back()->with('success', "Candidate profile [{$candidate->candidate_code}] updated.");
    }

    public function uploadResume(Request $request, Candidate $candidate)
    {
        $request->validate([
            'resume_file' => ['required', 'file', 'mimes:pdf,doc,docx,rtf,txt', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'resume_file.required' => 'Choose a resume file before uploading.',
            'resume_file.mimes' => 'Upload the resume as a PDF, DOC, DOCX, RTF, or TXT file.',
            'resume_file.max' => 'The resume file must be 10 MB or smaller.',
        ]);

        return DB::transaction(function () use ($request, $candidate) {
            $storageRecord = $this->storageService->storePrivateFile(
                $request->file('resume_file'),
                'resume',
                Candidate::class,
                $candidate->id
            );

            $candidate->resumeVersions()->update(['is_primary' => false]);
            $nextVersion = ((int) $candidate->resumeVersions()->max('version_number')) + 1;

            $version = ResumeVersion::create([
                'candidate_id' => $candidate->id,
                'version_number' => $nextVersion,
                'storage_record_id' => $storageRecord->id,
                'original_filename' => $storageRecord->original_name,
                'mime_type' => $storageRecord->mime_type,
                'file_size_bytes' => $storageRecord->size_bytes,
                'sha256_checksum' => $storageRecord->sha256_checksum,
                'is_primary' => true,
                'uploaded_by' => Auth::id(),
                'notes' => $request->input('notes', "Resume v{$nextVersion}"),
            ]);

            $candidate->update(['primary_resume_checksum' => $storageRecord->sha256_checksum]);

            AuditService::log('candidate.resume_uploaded', $candidate, $candidate->id, null, [
                'version_number' => $nextVersion,
                'sha256_checksum' => $storageRecord->sha256_checksum,
            ]);

            AuditService::recordActivity(
                $candidate,
                'resume_uploaded',
                "Uploaded Resume Version v{$nextVersion} ({$storageRecord->original_name})"
            );

            return back()->with('success', "Resume Version v{$version->version_number} uploaded privately and verified.");
        });
    }

    public function storeNote(Request $request, Candidate $candidate)
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:3000'],
            'job_requirement_id' => ['nullable', 'exists:job_requirements,id'],
            'is_client_visible' => ['nullable', 'boolean'],
        ], [
            'note.required' => 'Write a note before saving.',
        ]);

        $isClientVisible = $request->boolean('is_client_visible', false);

        CandidateNote::create([
            'candidate_id' => $candidate->id,
            'job_requirement_id' => $data['job_requirement_id'] ?? null,
            'user_id' => Auth::id(),
            'note' => $data['note'],
            'note_type' => $isClientVisible ? 'client_visible' : 'internal',
            'is_client_visible' => $isClientVisible,
        ]);

        AuditService::recordActivity(
            $candidate,
            'note_added',
            $isClientVisible ? 'Added client-visible candidate note' : 'Added internal recruiter note',
            null,
            $isClientVisible
        );

        return back()->with('success', 'Candidate note saved.');
    }

    public function merge(Request $request, Candidate $candidate)
    {
        $data = $request->validate([
            'duplicate_candidate_id' => ['required', 'exists:candidates,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ((int) $data['duplicate_candidate_id'] === (int) $candidate->id) {
            return back()->withErrors(['duplicate_candidate_id' => 'Cannot merge a candidate profile into itself.']);
        }

        $duplicate = RecruitmentAccess::query(Candidate::class)->findOrFail($data['duplicate_candidate_id']);
        $this->duplicateService->mergeCandidates(
            $candidate,
            $duplicate,
            $data['reason'] ?? 'Administrator merged duplicate candidate record'
        );

        return redirect()->route('candidates.show', $candidate)
            ->with('success', "Merged duplicate profile [{$duplicate->candidate_code}] into master profile [{$candidate->candidate_code}].");
    }

    public function downloadImportTemplate()
    {
        $headers = [
            'full_name', 'mobile_number', 'email', 'current_location', 'preferred_location',
            'current_employer', 'current_designation', 'total_experience', 'relevant_experience',
            'current_ctc', 'expected_ctc', 'notice_period_days', 'skills', 'source', 'consent_status', 'remarks',
        ];
        $sampleRows = [
            [
                'Karan Malhotra', '9820199001', 'karan.malhotra@example.com', 'Mumbai', 'Mumbai',
                'TechCore India', 'Senior Software Engineer', '6.5', '5.5',
                '1800000', '2400000', '30', 'PHP, Laravel, MySQL, AWS', 'Naukri', 'pending', 'Strong backend developer',
            ],
        ];

        $csv = $this->exportImportService->generateSafeCsv('candidate_template', 'orbitouch_candidate_import_template.csv', $headers, $sampleRows);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="orbitouch_candidate_import_template.csv"',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'import_file' => ['required', 'file', 'extensions:csv,txt', 'max:5120'],
        ], [
            'import_file.required' => 'Select a CSV file before starting the import.',
            'import_file.extensions' => 'Upload a valid .csv or .txt file.',
            'import_file.max' => 'The CSV file must be 5 MB or smaller.',
        ]);

        $file = $request->file('import_file');
        $path = $file->getRealPath();
        $rows = [];
        $headers = null;

        if (($handle = fopen($path, 'r')) !== false) {
            $firstLine = fgets($handle);
            rewind($handle);
            $delimiter = ($firstLine !== false && substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

            while (($data = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                if (! $headers) {
                    $headers = array_map(function ($h) {
                        $cleaned = strtolower(trim(ltrim((string) $h, "\xEF\xBB\xBF")));

                        return str_replace(' ', '_', $cleaned);
                    }, $data);

                    continue;
                }
                if (count($data) === 1 && trim((string) $data[0]) === '') {
                    continue;
                }
                if (count(array_filter($data, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                    continue;
                }
                $rowAssoc = [];
                foreach ($headers as $idx => $key) {
                    $rowAssoc[$key] = $data[$idx] ?? null;
                }
                $rows[] = $rowAssoc;
            }
            fclose($handle);
        }

        $hasNameColumn = $headers && count(array_intersect(['full_name', 'candidate_name', 'name'], $headers)) > 0;
        $hasPhoneColumn = $headers && count(array_intersect(['mobile_number', 'mobile', 'phone', 'phone_number'], $headers)) > 0;

        if (! $hasNameColumn || ! $hasPhoneColumn) {
            return back()->withErrors([
                'import_file' => 'The CSV file must include full_name and mobile_number columns. Download the CSV template to see the expected format.',
            ])->withInput();
        }

        if (empty($rows)) {
            return back()->withErrors([
                'import_file' => 'The selected CSV file does not contain any candidate rows to import.',
            ])->withInput();
        }

        $log = $this->exportImportService->importCandidatesFromRows($rows, $file->getClientOriginalName());

        session()->flash('import_errors', $log->error_details ?? []);

        return back()->with(
            'success',
            "Import completed: {$log->processed_rows} imported, {$log->duplicate_rows} duplicates skipped, {$log->failed_rows} invalid rows."
        );
    }

    public function export(Request $request)
    {
        $candidates = $this->filteredCandidatesQuery($request)
            ->with(['assignedRecruiter', 'skills'])
            ->orderByDesc('id')
            ->limit(5000)
            ->get();

        $headers = [
            'Candidate ID', 'Full Name', 'Mobile Number', 'Email', 'Current Location',
            'Current Employer', 'Current Designation', 'Total Experience (Yrs)',
            'Current CTC', 'Expected CTC', 'Currency', 'Notice Period (Days)',
            'Skills', 'Source', 'Consent Status', 'Current Stage', 'Assigned Recruiter',
        ];

        $rows = $candidates->map(fn (Candidate $c) => [
            $c->candidate_code,
            $c->full_name,
            $c->mobile_number,
            $c->email,
            $c->current_location,
            $c->current_employer,
            $c->current_designation,
            $c->total_experience,
            $c->current_ctc,
            $c->expected_ctc,
            $c->currency,
            $c->notice_period_days,
            $c->skills->pluck('skill_name')->implode(', '),
            $c->source,
            $c->consent_status,
            $c->current_stage,
            $c->assignedRecruiter?->name,
        ]);

        $filename = 'orbitouch_candidates_'.now()->format('Ymd_His').'.csv';
        $csv = $this->exportImportService->generateSafeCsv('candidates', $filename, $headers, $rows);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
