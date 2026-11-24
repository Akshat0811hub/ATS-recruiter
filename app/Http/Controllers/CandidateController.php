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
use App\Services\StorageManagementService;
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

    public function index(Request $request)
    {
        $query = Candidate::with(['assignedRecruiter', 'skills', 'primaryResume.storageRecord', 'applications.jobRequirement'])
            ->withCount(['applications', 'callLogs', 'clientSubmissions']);

        if ($search = $request->query('search')) {
            $normPhone = Candidate::normalizePhone($search);
            $query->where(function ($q) use ($search, $normPhone) {
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
        if ($location = $request->query('location')) {
            $query->where('current_location', 'like', "%{$location}%");
        }
        if ($recruiterId = $request->query('recruiter_id')) {
            $query->where('assigned_recruiter_id', $recruiterId);
        }
        if ($stage = $request->query('stage')) {
            $query->where('current_stage', $stage);
        }
        if ($maxNotice = $request->query('max_notice_days')) {
            $query->where('notice_period_days', '<=', (int) $maxNotice);
        }

        $candidates = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $recruiters = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['recruiter', 'team_leader', 'manager']))->where('is_active', true)->get();
        $activeJobs = JobRequirement::with('client')->whereNotIn('status', ['successfully_closed', 'cancelled'])->orderByDesc('id')->get();
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
        );

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
            'mobile_number' => ['required', 'string', 'max:40'],
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
            'resume_file' => ['nullable', 'file', 'max:10240'],
            'job_requirement_id' => ['nullable', 'exists:job_requirements,id'],
            'confirm_duplicate_override' => ['nullable', 'boolean'],
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

        if ($duplicates->isNotEmpty() && ! $request->boolean('confirm_duplicate_override')) {
            $firstMatch = $duplicates->first();
            $reasons = implode(', ', $firstMatch->getAttribute('duplicate_reasons') ?? ['Contact Match']);

            return back()
                ->withInput()
                ->with('duplicate_warning', "Potential duplicate candidate detected: {$firstMatch->full_name} ({$firstMatch->candidate_code}) via [{$reasons}]. Please review the existing master profile or confirm override.");
        }

        return DB::transaction(function () use ($request, $data, $resumeChecksum) {
            $tags = ! empty($data['tags_csv'])
                ? array_values(array_filter(array_map('trim', explode(',', $data['tags_csv']))))
                : [];

            $candidate = Candidate::create([
                'full_name' => $data['full_name'],
                'mobile_number' => $data['mobile_number'],
                'alternate_number' => $data['alternate_number'] ?? null,
                'email' => $data['email'] ?? null,
                'current_location' => $data['current_location'] ?? 'Mumbai',
                'preferred_location' => $data['preferred_location'] ?? ($data['current_location'] ?? 'Mumbai'),
                'country' => $data['country'] ?? 'India',
                'current_employer' => $data['current_employer'] ?? null,
                'current_designation' => $data['current_designation'] ?? null,
                'total_experience' => $data['total_experience'] ?? 0,
                'relevant_experience' => $data['relevant_experience'] ?? ($data['total_experience'] ?? 0),
                'qualifications' => $data['qualifications'] ?? null,
                'industry' => $data['industry'] ?? null,
                'current_ctc' => $data['current_ctc'] ?? null,
                'expected_ctc' => $data['expected_ctc'] ?? null,
                'currency' => $data['currency'] ?? 'INR',
                'notice_period_days' => $data['notice_period_days'] ?? 30,
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
                'consent_status' => $data['consent_status'] ?? 'granted',
                'consent_recorded_at' => now(),
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
                $job = JobRequirement::findOrFail($data['job_requirement_id']);
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
            'applications.jobRequirement.client',
            'applications.recruiter',
            'applications.latestScreening',
            'callLogs.recruiter',
            'callLogs.jobRequirement',
            'clientSubmissions.jobRequirement',
            'clientSubmissions.client',
            'interviewRecords.jobRequirement',
            'offers.jobRequirement',
            'joiningRecords.jobRequirement',
            'tasks.assignee',
            'activityLogs.user',
        ]);

        $potentialDuplicates = $this->duplicateService->findDuplicates(
            $candidate->mobile_number,
            $candidate->email,
            $candidate->alternate_number,
            $candidate->primary_resume_checksum,
            $candidate->id
        );

        $activeJobs = JobRequirement::with('client')
            ->whereNotIn('status', ['successfully_closed', 'cancelled'])
            ->orderByDesc('id')
            ->get();

        return view('candidates.show', compact('candidate', 'potentialDuplicates', 'activeJobs'));
    }

    public function preview(Candidate $candidate)
    {
        $candidate->load(['assignedRecruiter', 'skills', 'primaryResume', 'applications.jobRequirement.client', 'callLogs' => fn ($q) => $q->limit(3)]);

        return response()->json([
            'id' => $candidate->id,
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
        // Enforce recruiter authorisation check: a recruiter cannot modify another recruiter's restricted candidate unless assigned to one of their jobs or admin/manager/TL
        if ($user->isRecruiter() && $candidate->assigned_recruiter_id && (int) $candidate->assigned_recruiter_id !== (int) $user->id) {
            $worksOnSameCandidate = $candidate->applications()->where('recruiter_id', $user->id)->exists();
            if (! $worksOnSameCandidate) {
                abort(403, 'Unauthorised: This candidate profile is owned by another recruiter.');
            }
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:180'],
            'mobile_number' => ['required', 'string', 'max:40'],
            'alternate_number' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'],
            'current_location' => ['nullable', 'string', 'max:150'],
            'preferred_location' => ['nullable', 'string', 'max:180'],
            'current_employer' => ['nullable', 'string', 'max:180'],
            'current_designation' => ['nullable', 'string', 'max:160'],
            'total_experience' => ['nullable', 'numeric', 'min:0'],
            'relevant_experience' => ['nullable', 'numeric', 'min:0'],
            'qualifications' => ['nullable', 'string', 'max:255'],
            'current_ctc' => ['nullable', 'numeric', 'min:0'],
            'expected_ctc' => ['nullable', 'numeric', 'min:0'],
            'notice_period_days' => ['nullable', 'integer', 'min:0'],
            'is_serving_notice' => ['nullable', 'boolean'],
            'consent_status' => ['nullable', 'in:granted,pending,withdrawn,erasure_requested'],
            'availability_status' => ['nullable', 'string', 'max:60'],
            'remarks' => ['nullable', 'string'],
            'skills_csv' => ['nullable', 'string'],
        ]);

        $old = $candidate->only(['full_name', 'mobile_number', 'email', 'current_ctc', 'expected_ctc', 'notice_period_days']);
        $candidate->update(collect($data)->except('skills_csv')->toArray());

        if (isset($data['skills_csv'])) {
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
            'resume_file' => ['required', 'file', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:255'],
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

        $duplicate = Candidate::findOrFail($data['duplicate_candidate_id']);
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
            'current_ctc', 'expected_ctc', 'notice_period_days', 'skills', 'source', 'remarks',
        ];
        $sampleRows = [
            [
                'Karan Malhotra', '+91 9820199001', 'karan.malhotra@example.com', 'Mumbai', 'Mumbai',
                'TechCore India', 'Senior Software Engineer', '6.5', '5.5',
                '1800000', '2400000', '30', 'PHP, Laravel, MySQL, AWS', 'Naukri', 'Strong backend developer',
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
            'import_file' => ['required', 'file', 'max:5120'],
        ]);

        $file = $request->file('import_file');
        $path = $file->getRealPath();
        $rows = [];

        if (($handle = fopen($path, 'r')) !== false) {
            $headers = null;
            while (($data = fgetcsv($handle, 4096, ',')) !== false) {
                if (! $headers) {
                    $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $data);

                    continue;
                }
                if (count($data) === 1 && trim((string) $data[0]) === '') {
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

        $log = $this->exportImportService->importCandidatesFromRows($rows, $file->getClientOriginalName());

        return back()->with(
            'success',
            "Import completed: {$log->processed_rows} imported, {$log->duplicate_rows} duplicates skipped, {$log->failed_rows} invalid rows."
        );
    }

    public function export(Request $request)
    {
        $candidates = Candidate::with(['assignedRecruiter', 'skills'])->orderByDesc('id')->limit(5000)->get();

        $headers = [
            'Candidate ID', 'Full Name', 'Mobile Number', 'Email', 'Current Location',
            'Current Employer', 'Current Designation', 'Total Experience (Yrs)',
            'Current CTC', 'Expected CTC', 'Currency', 'Notice Period (Days)',
            'Skills', 'Source', 'Current Stage', 'Assigned Recruiter',
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
