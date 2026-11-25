@extends('layouts.app')
@section('title', $client->company_name . ' — Client Portal')
@section('page_title', $client->company_name . ' — Dedicated Client Hiring Portal')
@section('page_subtitle', 'Isolated Client Workspace (' . $client->client_code . ') • Submit Requirements, Shortlist Profiles & Track Interviews')

@section('content')
<div x-data="{ showNewReqModal: {{ $errors->any() && old('title') !== null ? 'true' : 'false' }} }">
    <!-- Client KPI Summary -->
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 mb-6 text-xs">
        <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <div class="text-[10px] text-slate-400 uppercase">Requirements</div>
            <div class="text-lg font-bold tabular-nums mt-0.5">{{ $stats['total_requirements'] }}</div>
        </div>
        <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <div class="text-[10px] text-slate-400 uppercase">Open Vacancies</div>
            <div class="text-lg font-bold tabular-nums text-[#1E40AF] mt-0.5">{{ $stats['open_vacancies'] }}</div>
        </div>
        <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <div class="text-[10px] text-slate-400 uppercase">Submitted Profiles</div>
            <div class="text-lg font-bold tabular-nums mt-0.5">{{ $stats['profiles_submitted'] }}</div>
        </div>
        <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <div class="text-[10px] text-slate-400 uppercase">Pending Review</div>
            <div class="text-lg font-bold tabular-nums text-amber-600 mt-0.5">{{ $stats['pending_review'] }}</div>
        </div>
        <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <div class="text-[10px] text-slate-400 uppercase">Shortlisted</div>
            <div class="text-lg font-bold tabular-nums text-emerald-600 mt-0.5">{{ $stats['shortlisted'] }}</div>
        </div>
        <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <div class="text-[10px] text-slate-400 uppercase">Interviews</div>
            <div class="text-lg font-bold tabular-nums mt-0.5">{{ $stats['interviews_scheduled'] }}</div>
        </div>
        <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <div class="text-[10px] text-slate-400 uppercase">Confirmed Joined</div>
            <div class="text-lg font-bold tabular-nums text-emerald-700 mt-0.5">{{ $stats['candidates_joined'] }}</div>
        </div>
    </div>

    <!-- Top Actions -->
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('portal.compare') }}" class="px-4 py-2 rounded-lg bg-[#0B1528] text-white text-xs font-semibold">
            ⇄ Compare Submitted Candidates Side-by-Side
        </a>
        <button type="button" @click="showNewReqModal = true" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white text-xs font-semibold">
            + Submit New Hiring Requirement & Upload JD
        </button>
    </div>

    <!-- Submitted Candidate Profiles for Review & Shortlisting -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden mb-6">
        <div class="p-4 border-b border-slate-200 dark:border-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Submitted Candidate Profiles for Review ({{ $submissions->count() }})</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                    <tr>
                        <th class="px-4 py-3">Submission ID</th>
                        <th class="px-4 py-3">Candidate & Experience</th>
                        <th class="px-4 py-3">Vacancy</th>
                        <th class="px-4 py-3 text-right">Expected CTC & Notice</th>
                        <th class="px-4 py-3">Screening Summary</th>
                        <th class="px-4 py-3">Your Decision</th>
                        <th class="px-4 py-3">Shortlist / Reject / Share Slots</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
                    @forelse($submissions as $sub)
                        <tr>
                            <td class="px-4 py-3 font-mono font-bold text-[#D3003B]">{{ $sub->submission_code }}</td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $sub->snapshot_candidate_name }}</div>
                                <div class="text-[11px] text-slate-500">{{ $sub->snapshot_designation }} • {{ $sub->snapshot_relevant_experience }} Yrs Exp</div>
                                @if($sub->resumeVersion)
                                    <a href="{{ route('documents.download', $sub->resumeVersion->storage_record_id) }}" class="text-[10px] text-[#1E40AF] font-semibold hover:underline">⬇ Download Authorised Resume</a>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-medium">{{ $sub->jobRequirement?->title }} ({{ $sub->jobRequirement?->job_code }})</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="font-semibold">{{ $sub->snapshot_expected_ctc ? number_format((float)$sub->snapshot_expected_ctc).' '.$sub->snapshot_currency : '—' }}</div>
                                <div class="text-[11px] text-slate-500">{{ $sub->snapshot_notice_period_days !== null ? $sub->snapshot_notice_period_days.'d notice' : 'Notice N/A' }}</div>
                            </td>
                            <td class="px-4 py-3 max-w-xs text-slate-600 dark:text-slate-300">{{ $sub->screening_summary }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ in_array($sub->client_decision, ['shortlisted', 'interview_requested']) ? 'bg-emerald-100 text-emerald-800' : ($sub->client_decision === 'rejected' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                                    {{ \App\Models\ClientSubmission::DECISIONS[$sub->client_decision] ?? $sub->client_decision }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('portal.submissions.decision', $sub) }}" class="flex flex-wrap items-center gap-1.5">
                                    @csrf
                                    <select name="client_decision" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-[11px]">
                                        @foreach(\App\Models\ClientSubmission::DECISIONS as $dk => $dl)
                                            <option value="{{ $dk }}" {{ $sub->client_decision === $dk ? 'selected' : '' }}>{{ $dl }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="rejection_reason" placeholder="Feedback / Interview availability..." class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-transparent text-[11px] w-40">
                                    <button type="submit" class="px-2.5 py-1 rounded bg-[#D3003B] text-white font-semibold text-[11px]">Submit</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No profiles submitted to your organisation yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Your Requirements & Interview Schedule -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 text-xs">
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Our Active Hiring Requirements ({{ $requirements->count() }})</h3>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($requirements as $req)
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <div class="font-bold text-slate-900 dark:text-white"><span class="font-mono text-[#D3003B]">{{ $req->job_code }}</span> — {{ $req->title }}</div>
                            <div class="text-[11px] text-slate-500">{{ $req->location }} • Required: {{ $req->openings_count }} • Joined: {{ $req->joined_count }} • Remaining: <strong>{{ $req->remaining_count }}</strong></div>
                        </div>
                        <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-[10px] font-semibold uppercase">{{ str_replace('_', ' ', $req->status) }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Scheduled Interviews & Feedback ({{ $interviews->count() }})</h3>
            <div class="space-y-3">
                @foreach($interviews as $int)
                    <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800">
                        <div class="flex items-center justify-between">
                            <span class="font-bold">{{ $int->candidate?->full_name }} — {{ $int->round_name }}</span>
                            <span class="text-[10px] font-bold uppercase text-blue-600">{{ $int->status }}</span>
                        </div>
                        <div class="text-[11px] text-slate-500 mt-0.5">{{ $int->interview_date?->format('d M Y') }} at {{ $int->interview_time }} • {{ $int->jobRequirement?->title }}</div>
                        <form method="POST" action="{{ route('portal.interviews.result', $int) }}" class="mt-2 flex items-center gap-1.5">
                            @csrf
                            <select name="status" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-[10px]">
                                <option value="passed">Passed</option>
                                <option value="failed">Failed</option>
                                <option value="on_hold">On Hold</option>
                            </select>
                            <input type="text" name="feedback_summary" required placeholder="Interviewer evaluation..." class="flex-1 px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-transparent text-[10px]">
                            <button type="submit" class="px-2.5 py-1 rounded bg-[#0B1528] text-white font-semibold text-[10px]">Update Result</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Modal: Submit New Hiring Requirement -->
    <div x-show="showNewReqModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 overflow-y-auto">
        <div @click.away="showNewReqModal = false" class="w-full max-w-2xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 text-xs">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Submit New Hiring Requirement to OrbiTouch HR</h3>
                <button type="button" @click="showNewReqModal = false" class="text-slate-400">✕</button>
            </div>
            <form method="POST" action="{{ route('portal.requirements.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3.5">
                @csrf
                @if($errors->any())
                    <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-rose-800">
                        <p class="font-semibold">Please review the requirement details:</p>
                        <ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block font-semibold mb-1">Job Title *</label>
                        <input type="text" name="title" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Number of Openings *</label>
                        <input type="number" name="openings_count" value="2" min="1" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Location *</label>
                        <input type="text" name="location" value="{{ $client->city ?? 'Mumbai' }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Work Mode</label>
                        <select name="work_mode" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            <option value="onsite">Onsite</option>
                            <option value="hybrid">Hybrid</option>
                            <option value="remote">Remote</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Interview Rounds</label>
                        <input type="number" name="interview_rounds_count" value="3" min="1" max="15" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Min Experience (Yrs) *</label>
                        <input type="number" step="0.5" name="min_experience" value="3" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block font-semibold mb-1">Required Skills (CSV)</label>
                        <input type="text" name="required_skills_csv" placeholder="Java, Spring Boot, Microservices" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                </div>
                <div>
                    <label class="block font-semibold mb-1">Job Description *</label>
                    <textarea name="description" rows="3" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent"></textarea>
                </div>
                <div>
                    <label class="block font-semibold mb-1">Upload Official Job Description (PDF / DOCX)</label>
                    <input type="file" name="jd_file" class="w-full">
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                    <button type="button" @click="showNewReqModal = false" class="px-3.5 py-2 rounded-lg border border-slate-300">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">Submit Requirement</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
