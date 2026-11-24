@extends('layouts.app')
@section('title', $candidate->full_name . ' (' . $candidate->candidate_code . ')')
@section('page_title', $candidate->full_name . ' — ' . $candidate->candidate_code)
@section('page_subtitle', ($candidate->current_designation ?? 'Candidate') . ' at ' . ($candidate->current_employer ?? 'N/A') . ' • Assigned Recruiter: ' . ($candidate->assignedRecruiter?->name ?? 'Unassigned'))

@section('content')
<!-- Candidate 360° Header & Quick Action Bar -->
<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5 mb-6 text-xs">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2.5">
                <span class="px-2.5 py-0.5 rounded bg-[#0B1528] text-white font-mono font-bold">{{ $candidate->candidate_code }}</span>
                <span class="px-2.5 py-0.5 rounded bg-blue-100 text-blue-800 font-semibold uppercase text-[10px]">{{ str_replace('_', ' ', $candidate->current_stage) }}</span>
                <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-medium text-[10px]">DPDP Consent: {{ strtoupper($candidate->consent_status) }}</span>
            </div>
            <div class="text-slate-600 dark:text-slate-300 mt-2 flex flex-wrap items-center gap-4">
                <a href="tel:{{ $candidate->mobile_number }}" class="font-bold text-[#1E40AF] hover:underline">📞 {{ $candidate->mobile_number }} (Click-to-Call)</a>
                @if($candidate->email)<span>✉️ {{ $candidate->email }}</span>@endif
                <span>📍 {{ $candidate->current_location }} (Pref: {{ $candidate->preferred_location }})</span>
                <span class="tabular-nums">💼 {{ $candidate->total_experience }} Yrs Exp ({{ $candidate->relevant_experience }} Relevant)</span>
                <span class="tabular-nums">💰 CTC: {{ number_format((float)$candidate->current_ctc) }} → <strong>{{ number_format((float)$candidate->expected_ctc) }} {{ $candidate->currency }}</strong></span>
                <span class="tabular-nums">⏱️ Notice: <strong>{{ $candidate->notice_period_days }} Days</strong></span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if($candidate->primaryResume)
                <a href="{{ route('documents.download', $candidate->primaryResume->storage_record_id) }}" class="px-3.5 py-2 rounded-lg bg-[#0B1528] text-white font-semibold">
                    ⬇ Download Resume (v{{ $candidate->primaryResume->version_number }})
                </a>
            @endif
        </div>
    </div>

    <!-- Duplicate Candidate Alert & Safe Administrator Merge -->
    @if($potentialDuplicates->isNotEmpty())
        <div class="mt-4 p-4 rounded-xl bg-amber-50 dark:bg-amber-950/50 border border-amber-300 dark:border-amber-700">
            <div class="font-bold text-amber-900 dark:text-amber-200 mb-2">⚠️ Potential Duplicate Candidate Record(s) Detected ({{ $potentialDuplicates->count() }})</div>
            <div class="space-y-2">
                @foreach($potentialDuplicates as $dup)
                    <div class="flex flex-wrap items-center justify-between gap-2 p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-amber-200">
                        <div>
                            <a href="{{ route('candidates.show', $dup) }}" class="font-bold text-[#D3003B] hover:underline">{{ $dup->candidate_code }} — {{ $dup->full_name }}</a>
                            <span class="text-slate-500 ml-2">{{ $dup->mobile_number }} • {{ $dup->email }}</span>
                            <span class="ml-2 px-2 py-0.5 rounded bg-amber-100 text-amber-800 text-[10px] font-semibold">{{ implode(', ', $dup->getAttribute('duplicate_reasons') ?? []) }}</span>
                        </div>
                        @if(auth()->user()->hasRole(['super_admin', 'manager']))
                            <form method="POST" action="{{ route('candidates.merge', $candidate) }}">
                                @csrf
                                <input type="hidden" name="duplicate_candidate_id" value="{{ $dup->id }}">
                                <button type="submit" class="px-3 py-1 rounded bg-[#D3003B] text-white font-semibold text-[11px]">
                                    Merge {{ $dup->candidate_code }} into {{ $candidate->candidate_code }}
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 text-xs">
    <!-- Left Column: Job Applications, Calling & Screening, Resume Versions -->
    <div class="lg:col-span-7 space-y-6">
        <!-- Associate Candidate with a Job Requirement -->
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Job-Specific Applications ({{ $candidate->applications->count() }})</h2>
            <form method="POST" action="{{ route('applications.store') }}" class="mb-4 flex flex-wrap items-center gap-2 pb-4 border-b border-slate-100 dark:border-slate-800">
                @csrf
                <input type="hidden" name="candidate_id" value="{{ $candidate->id }}">
                <select name="job_requirement_id" required class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                    <option value="">-- Select Active Job Requirement to Associate --</option>
                    @foreach($activeJobs as $aj)
                        <option value="{{ $aj->id }}">{{ $aj->job_code }} — {{ $aj->title }} ({{ $aj->client?->company_name }})</option>
                    @endforeach
                </select>
                <button type="submit" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">+ Associate with Job</button>
            </form>

            <div class="space-y-4">
                @forelse($candidate->applications as $app)
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <span class="font-mono font-bold text-[#D3003B]">{{ $app->application_code }}</span>
                                <a href="{{ route('jobs.show', $app->jobRequirement) }}" class="font-bold text-slate-900 dark:text-white ml-1 hover:underline">{{ $app->jobRequirement?->title }}</a>
                                <div class="text-[11px] text-slate-500">{{ $app->jobRequirement?->client?->company_name }} • Recruiter Owner: <strong>{{ $app->recruiter?->name ?? 'Unassigned' }}</strong></div>
                            </div>
                            <span class="px-2.5 py-1 rounded-full bg-blue-100 text-blue-800 font-bold text-[10px] uppercase">{{ $app->stageLabel() }}</span>
                        </div>

                        <!-- Quick Stage Transition, Screening & Client Submission Forms -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2 border-t border-slate-200/70 dark:border-slate-700">
                            <!-- Stage Transition -->
                            <form method="POST" action="{{ route('applications.transition', $app) }}" class="flex items-center gap-1.5">
                                @csrf
                                <input type="hidden" name="lock_version" value="{{ $app->lock_version }}">
                                <select name="target_stage" class="flex-1 px-2.5 py-1.5 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                                    @foreach(\App\Models\CandidateApplication::allStages() as $sKey => $sLabel)
                                        <option value="{{ $sKey }}" {{ $app->pipeline_stage === $sKey ? 'selected' : '' }}>{{ $sLabel }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="px-2.5 py-1.5 rounded bg-[#0B1528] text-white font-semibold">Move</button>
                            </form>

                            <!-- Submit Profile to Client -->
                            <form method="POST" action="{{ route('applications.submit', $app) }}" class="flex items-center gap-1.5">
                                @csrf
                                <input type="text" name="screening_summary" required placeholder="Screening summary for client..." class="flex-1 px-2.5 py-1.5 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                                <button type="submit" class="px-2.5 py-1.5 rounded bg-[#D3003B] text-white font-semibold">Submit to Client</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="text-slate-400">Not associated with any job requirement yet.</div>
                @endforelse
            </div>
        </div>

        <!-- Record Call Discussion (Chronological Communication History) -->
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">📞 Record Call Discussion & Screening Notes</h2>
            <form method="POST" action="{{ route('candidates.calls.store', $candidate) }}" class="space-y-3 pb-5 border-b border-slate-200 dark:border-slate-800">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block font-semibold mb-1">Call Outcome *</label>
                        <select name="outcome" required class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            @foreach(\App\Models\CallLog::OUTCOMES as $oKey => $oLabel)
                                <option value="{{ $oKey }}">{{ $oLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Associated Job</label>
                        <select name="job_requirement_id" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            <option value="">-- General Call --</option>
                            @foreach($candidate->applications as $app)
                                <option value="{{ $app->job_requirement_id }}">{{ $app->jobRequirement?->job_code }} — {{ $app->jobRequirement?->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Duration (Seconds)</label>
                        <input type="number" name="duration_seconds" value="300" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Confirmed Expected CTC</label>
                        <input type="number" name="expected_ctc" value="{{ $candidate->expected_ctc }}" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Notice Period (Days)</label>
                        <input type="number" name="notice_period_days" value="{{ $candidate->notice_period_days }}" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Next Follow-up Date</label>
                        <input type="date" name="follow_up_at" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                </div>
                <div>
                    <label class="block font-semibold mb-1">Call Discussion Summary & Eligibility Remarks *</label>
                    <textarea name="discussion_summary" rows="2" required placeholder="Document candidate interest, technical screening answers, salary breakdown, and interview availability..." class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent"></textarea>
                </div>
                <button type="submit" class="px-4 py-2 rounded-lg bg-[#0B1528] text-white font-semibold">Record Call in Chronological History</button>
            </form>

            <!-- Chronological Call History -->
            <div class="mt-4 space-y-3">
                <div class="font-bold text-slate-700 dark:text-slate-300">Chronological Call Logs ({{ $candidate->callLogs->count() }})</div>
                @forelse($candidate->callLogs as $call)
                    <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-[#D3003B]">{{ \App\Models\CallLog::OUTCOMES[$call->outcome] ?? $call->outcome }}</span>
                            <span class="text-[11px] text-slate-400 tabular-nums">{{ $call->called_at?->format('d M Y, H:i') }} • {{ $call->recruiter?->name }}</span>
                        </div>
                        <p class="mt-1 text-slate-700 dark:text-slate-300">{{ $call->discussion_summary }}</p>
                    </div>
                @empty
                    <div class="text-slate-400">No calls recorded yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Right Column: Edit Master Details, Versioned Resumes, Notes & Activity Timeline -->
    <div class="lg:col-span-5 space-y-6">
        <!-- Private Versioned Resume Vault -->
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Private Resume Version History ({{ $candidate->resumeVersions->count() }})</h2>
            <form method="POST" action="{{ route('candidates.resumes.store', $candidate) }}" enctype="multipart/form-data" class="mb-4 flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                @csrf
                <input type="file" name="resume_file" required class="flex-1 text-[11px]">
                <button type="submit" class="px-3 py-1.5 rounded-lg bg-[#D3003B] text-white font-semibold">Upload New Version</button>
            </form>
            <div class="space-y-2">
                @forelse($candidate->resumeVersions as $rv)
                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                        <div>
                            <div class="font-semibold">v{{ $rv->version_number }} — {{ $rv->original_filename }} @if($rv->is_primary)<span class="text-[10px] text-emerald-600 font-bold">(Primary)</span>@endif</div>
                            <div class="text-[10px] text-slate-400 font-mono">SHA-256: {{ substr($rv->sha256_checksum, 0, 16) }}... • {{ $rv->created_at?->format('d M Y') }}</div>
                        </div>
                        <a href="{{ route('documents.download', $rv->storage_record_id) }}" class="px-2.5 py-1 rounded bg-[#0B1528] text-white font-semibold text-[10px]">Download</a>
                    </div>
                @empty
                    <div class="text-slate-400">No resume uploaded yet.</div>
                @endforelse
            </div>
        </div>

        <!-- Candidate Notes (Internal vs Client Visible) -->
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Recruiter Notes ({{ $candidate->notes->count() }})</h2>
            <form method="POST" action="{{ route('candidates.notes.store', $candidate) }}" class="space-y-2.5 mb-4">
                @csrf
                <textarea name="note" rows="2" required placeholder="Add internal note or client-visible summary..." class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent"></textarea>
                <div class="flex items-center justify-between">
                    <label class="inline-flex items-center gap-1.5 text-[11px] text-slate-600">
                        <input type="checkbox" name="is_client_visible" value="1">
                        <span>Make visible to external Client HR in Portal</span>
                    </label>
                    <button type="submit" class="px-3 py-1.5 rounded bg-[#0B1528] text-white font-semibold">Add Note</button>
                </div>
            </form>
            <div class="space-y-2 max-h-48 overflow-y-auto">
                @foreach($candidate->notes as $nt)
                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800">
                        <div class="flex items-center justify-between text-[10px] text-slate-400 mb-1">
                            <span>{{ $nt->user?->name ?? 'System' }} • {{ $nt->created_at?->format('d M Y H:i') }}</span>
                            <span class="px-1.5 py-0.5 rounded font-semibold {{ $nt->is_client_visible ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                                {{ $nt->is_client_visible ? 'Client Visible' : 'Internal Only' }}
                            </span>
                        </div>
                        <p>{{ $nt->note }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Activity Timeline -->
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">360° Activity Timeline</h2>
            <div class="space-y-3 max-h-64 overflow-y-auto border-l-2 border-slate-200 dark:border-slate-800 pl-3.5">
                @forelse($candidate->activityLogs as $act)
                    <div>
                        <div class="font-semibold text-slate-800 dark:text-slate-200">{{ $act->title }}</div>
                        @if($act->description)<div class="text-[11px] text-slate-500">{{ $act->description }}</div>@endif
                        <div class="text-[10px] text-slate-400 tabular-nums">{{ $act->created_at?->format('d M Y, H:i') }} • {{ $act->user?->name ?? 'System' }}</div>
                    </div>
                @empty
                    <div class="text-slate-400">No timeline activity yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
