@extends('layouts.app')
@section('title', 'Compare Submitted Candidates — Client Portal')
@section('page_title', 'Side-by-Side Candidate Comparison (' . $client->company_name . ')')
@section('page_subtitle', 'Compare submitted experience, salary expectations, notice periods, skills, and interview progression')

@section('content')
<div class="mb-4">
    <a href="{{ route('portal.dashboard') }}" class="text-xs text-[#1E40AF] font-semibold hover:underline">← Back to Client Dashboard</a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
    @forelse($submissions as $sub)
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5 flex flex-col justify-between space-y-4">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="font-mono font-bold text-[#D3003B]">{{ $sub->submission_code }}</span>
                    <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800 text-[10px] font-bold uppercase">{{ \App\Models\ClientSubmission::DECISIONS[$sub->client_decision] ?? $sub->client_decision }}</span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $sub->snapshot_candidate_name }}</h3>
                    <p class="text-slate-500">{{ $sub->snapshot_designation }} • {{ $sub->snapshot_employer }}</p>
                    <p class="text-[11px] text-[#1E40AF] font-semibold mt-0.5">Vacancy: {{ $sub->jobRequirement?->title }}</p>
                </div>

                <div class="grid grid-cols-2 gap-2.5 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 tabular-nums">
                    <div>
                        <span class="text-[10px] text-slate-400 block">Relevant / Total Exp</span>
                        <strong>{{ $sub->snapshot_relevant_experience ?? 0 }} / {{ $sub->snapshot_total_experience ?? 0 }} Yrs</strong>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 block">Notice Period</span>
                        <strong>{{ $sub->snapshot_notice_period_days !== null ? $sub->snapshot_notice_period_days.' Days' : 'Not provided' }}</strong>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 block">Current CTC</span>
                        <strong>{{ $sub->snapshot_current_ctc ? number_format((float)$sub->snapshot_current_ctc) : '—' }}</strong>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 block">Expected CTC</span>
                        <strong class="text-emerald-600">{{ $sub->snapshot_expected_ctc ? number_format((float)$sub->snapshot_expected_ctc).' '.$sub->snapshot_currency : '—' }}</strong>
                    </div>
                </div>

                <div>
                    <div class="text-[10px] font-semibold uppercase text-slate-400 mb-1">Key Skills</div>
                    <div>
                        @foreach($sub->candidate?->skills ?? [] as $sk)
                            <span class="inline-block px-2 py-0.5 mr-1 mb-1 rounded bg-slate-100 dark:bg-slate-800 text-[10px] font-medium">{{ $sk->skill_name }}</span>
                        @endforeach
                    </div>
                </div>

                <div>
                    <div class="text-[10px] font-semibold uppercase text-slate-400 mb-1">Screening Summary</div>
                    <p class="text-slate-600 dark:text-slate-300">{{ $sub->screening_summary }}</p>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-2">
                @if($sub->resumeVersion)
                    <a href="{{ route('documents.download', $sub->resumeVersion->storage_record_id) }}" class="block text-center py-2 rounded-lg bg-[#0B1528] text-white font-semibold">
                        ⬇ Download Authorised Resume
                    </a>
                @endif
                <form method="POST" action="{{ route('portal.submissions.decision', $sub) }}" class="flex items-center gap-1.5">
                    @csrf
                    <select name="client_decision" class="flex-1 px-2 py-1.5 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                        @foreach(\App\Models\ClientSubmission::DECISIONS as $dk => $dl)
                            <option value="{{ $dk }}" {{ $sub->client_decision === $dk ? 'selected' : '' }}>{{ $dl }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-3 py-1.5 rounded bg-[#D3003B] text-white font-semibold">Decide</button>
                </form>
            </div>
        </div>
    @empty
        <div class="col-span-3 p-8 text-center text-slate-400 bg-white dark:bg-slate-900 rounded-xl border border-slate-200">No submitted candidates available for comparison.</div>
    @endforelse
</div>
@endsection
