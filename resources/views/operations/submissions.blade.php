@extends('layouts.app')
@section('title', 'Submissions')
@section('page_title', 'Submissions')
@section('page_subtitle', 'Send shortlisted profiles to clients and track their feedback.')

@section('content')
<div x-data="{ showBulkModal: false }">
    <!-- Filter & Bulk Submit Toolbar -->
    <div class="mb-5 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
        <form method="GET" action="{{ route('submissions.index') }}" class="flex flex-wrap items-center gap-2.5">
            <select name="decision" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                <option value="">All Client Decisions</option>
                @foreach(\App\Models\ClientSubmission::DECISIONS as $dk => $dl)
                    <option value="{{ $dk }}" {{ request('decision') === $dk ? 'selected' : '' }}>{{ $dl }}</option>
                @endforeach
            </select>
            <select name="client_id" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                <option value="">All Clients</option>
                @foreach($clients as $cl)
                    <option value="{{ $cl->id }}" {{ request('client_id') == $cl->id ? 'selected' : '' }}>{{ $cl->company_name }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-[#0B1528] text-white font-semibold">Filter</button>
        </form>
        <button type="button" @click="showBulkModal = true" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">
            + Bulk Submit Screened Profiles
        </button>
    </div>

    <!-- Submissions Table -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                    <tr>
                        <th class="px-4 py-3">Submission ID</th>
                        <th class="px-4 py-3">Submitted Snapshot</th>
                        <th class="px-4 py-3">Job & Client</th>
                        <th class="px-4 py-3 text-right">CTC / Notice Snapshot</th>
                        <th class="px-4 py-3">Client Decision</th>
                        <th class="px-4 py-3">Record Client Feedback</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
                    @forelse($submissions as $sub)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-mono font-bold text-[#D3003B]">{{ $sub->submission_code }}</td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $sub->snapshot_candidate_name }}</div>
                                <div class="text-[11px] text-slate-500">{{ $sub->snapshot_designation }} • {{ $sub->snapshot_relevant_experience }} Yrs Rel Exp</div>
                                @if($sub->resumeVersion)
                                    <a href="{{ route('documents.download', $sub->resumeVersion->storage_record_id) }}" class="text-[10px] text-[#1E40AF] font-semibold hover:underline">Download Submitted CV (v{{ $sub->resumeVersion->version_number }})</a>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold">{{ $sub->jobRequirement?->title }}</div>
                                <div class="text-[11px] text-slate-500">{{ $sub->client?->company_name }} • By {{ $sub->recruiter?->name }}</div>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div>{{ $sub->snapshot_current_ctc ? number_format((float)$sub->snapshot_current_ctc) : '—' }} → <strong>{{ $sub->snapshot_expected_ctc ? number_format((float)$sub->snapshot_expected_ctc) : '—' }}</strong></div>
                                <div class="text-[11px] text-slate-500">{{ $sub->snapshot_notice_period_days !== null ? $sub->snapshot_notice_period_days.'d notice' : 'Notice N/A' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ in_array($sub->client_decision, ['shortlisted', 'interview_requested']) ? 'bg-emerald-100 text-emerald-800' : ($sub->client_decision === 'rejected' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                                    {{ \App\Models\ClientSubmission::DECISIONS[$sub->client_decision] ?? $sub->client_decision }}
                                </span>
                                @if($sub->rejection_reason)
                                    <div class="text-[10px] text-rose-600 mt-1">Reason: {{ $sub->rejection_reason }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('submissions.decision', $sub) }}" class="flex flex-wrap items-center gap-1.5">
                                    @csrf
                                    <select name="client_decision" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-[11px]">
                                        @foreach(\App\Models\ClientSubmission::DECISIONS as $dk => $dl)
                                            <option value="{{ $dk }}" {{ $sub->client_decision === $dk ? 'selected' : '' }}>{{ $dl }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="rejection_reason" placeholder="Feedback / Reason" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-transparent text-[11px] w-32">
                                    <button type="submit" class="px-2.5 py-1 rounded bg-[#0B1528] text-white font-semibold text-[11px]">Save</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No profile submissions recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-800">
            {{ $submissions->links() }}
        </div>
    </div>

    <!-- Bulk Profile Submission Modal -->
    <div x-show="showBulkModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4">
        <div @click.away="showBulkModal = false" class="w-full max-w-2xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 text-xs">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Bulk Submit Screened Candidate Profiles</h3>
                <button type="button" @click="showBulkModal = false" class="text-slate-400">✕</button>
            </div>
            <form method="POST" action="{{ route('submissions.bulk') }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="block font-semibold mb-1">Select Screened / Interested Applications *</label>
                    <select name="application_ids[]" multiple required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 h-40">
                        @foreach($screenedApplications as $sa)
                            <option value="{{ $sa->id }}">{{ $sa->application_code }} — {{ $sa->candidate?->full_name }} for {{ $sa->jobRequirement?->title }} ({{ $sa->jobRequirement?->client?->company_name }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-semibold mb-1">Batch Screening Summary *</label>
                    <textarea name="screening_summary" rows="2" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">Pre-screened on technical competencies, salary compatibility, and notice period.</textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="showBulkModal = false" class="px-3.5 py-2 rounded-lg border border-slate-300">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">Submit Selected Profiles</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
