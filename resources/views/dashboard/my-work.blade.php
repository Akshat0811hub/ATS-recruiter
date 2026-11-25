@extends('layouts.app')
@section('title', 'My work')
@section('page_title', 'My work')
@section('page_subtitle', 'Your assigned jobs, daily priorities and upcoming follow-ups.')

@section('content')
<!-- Daily Target Progress Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6 text-xs">
    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="flex items-center justify-between">
            <span class="text-slate-500 font-medium">Daily Sourcing Target</span>
            <span class="font-bold text-sm tabular-nums text-slate-900 dark:text-white">{{ $sourcedToday }} / {{ $user->daily_sourcing_target }}</span>
        </div>
        <div class="w-full h-2 bg-slate-100 dark:bg-slate-800 rounded-full mt-2 overflow-hidden">
            <div class="h-full bg-[#1E40AF]" style="width: {{ min(100, round(($sourcedToday / max(1, $user->daily_sourcing_target)) * 100)) }}%"></div>
        </div>
    </div>
    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="flex items-center justify-between">
            <span class="text-slate-500 font-medium">Daily Calling Target</span>
            <span class="font-bold text-sm tabular-nums text-slate-900 dark:text-white">{{ $callsToday }} / {{ $user->daily_call_target }}</span>
        </div>
        <div class="w-full h-2 bg-slate-100 dark:bg-slate-800 rounded-full mt-2 overflow-hidden">
            <div class="h-full bg-[#D3003B]" style="width: {{ min(100, round(($callsToday / max(1, $user->daily_call_target)) * 100)) }}%"></div>
        </div>
    </div>
    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="flex items-center justify-between">
            <span class="text-slate-500 font-medium">Daily Profile Submissions</span>
            <span class="font-bold text-sm tabular-nums text-slate-900 dark:text-white">{{ $submittedToday }} / {{ $user->daily_submission_target }}</span>
        </div>
        <div class="w-full h-2 bg-slate-100 dark:bg-slate-800 rounded-full mt-2 overflow-hidden">
            <div class="h-full bg-emerald-600" style="width: {{ min(100, round(($submittedToday / max(1, $user->daily_submission_target)) * 100)) }}%"></div>
        </div>
    </div>
</div>

<!-- Quick Candidate Entry & Quick Call Note -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
    <!-- Quick Candidate Creation -->
    <div class="lg:col-span-6 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">⚡ Quick Candidate Entry</h2>
        <form method="POST" action="{{ route('candidates.store') }}" enctype="multipart/form-data" class="space-y-3 text-xs">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold mb-1">Full Name *</label>
                    <input type="text" name="full_name" required class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Mobile Number *</label>
                    <input type="text" name="mobile_number" required placeholder="+91 98XXXXXXXX" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Email</label>
                    <input type="email" name="email" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Associate with Assigned Job</label>
                    <select name="job_requirement_id" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                        <option value="">-- Master DB Only --</option>
                        @foreach($assignedJobs as $asg)
                            @if($asg->jobRequirement)
                                <option value="{{ $asg->jobRequirement->id }}">{{ $asg->jobRequirement->job_code }} — {{ $asg->jobRequirement->title }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-semibold mb-1">Total Exp (Yrs)</label>
                    <input type="number" step="0.1" min="0" name="total_experience" value="{{ old('total_experience') }}" placeholder="Optional" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Consent Status</label>
                    <select name="consent_status" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                        <option value="pending" @selected(old('consent_status', 'pending') === 'pending')>Consent Pending</option>
                        <option value="granted" @selected(old('consent_status') === 'granted')>Consent Granted</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block font-semibold mb-1">Private Resume (PDF/DOCX)</label>
                    <input type="file" name="resume_file" accept=".pdf,.doc,.docx" class="w-full text-[11px]">
                </div>
            </div>
            <button type="submit" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">Save Candidate & Check Duplicates</button>
        </form>
    </div>

    <!--Assigned Job Requirements -->
    <div class="lg:col-span-6 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">My Assigned Job Requirements ({{ $assignedJobs->count() }})</h2>
        <div class="divide-y divide-slate-100 dark:divide-slate-800 text-xs max-h-64 overflow-y-auto">
            @forelse($assignedJobs as $asg)
                @if($asg->jobRequirement)
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <a href="{{ route('jobs.show', $asg->jobRequirement) }}" class="font-semibold text-slate-900 dark:text-white hover:text-[#D3003B]">
                                <span class="font-mono text-[#D3003B]">{{ $asg->jobRequirement->job_code }}</span> — {{ $asg->jobRequirement->title }}
                            </a>
                            <div class="text-[11px] text-slate-500">
                                {{ $asg->jobRequirement->client?->company_name }} • Remaining: <strong>{{ $asg->jobRequirement->remaining_count }}</strong> / {{ $asg->jobRequirement->openings_count }}
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase {{ $asg->is_primary ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-700' }}">
                            {{ $asg->is_primary ? 'Primary' : 'Secondary' }}
                        </span>
                    </div>
                @endif
            @empty
                <div class="py-6 text-center text-slate-400">No job requirements assigned yet.</div>
            @endforelse
        </div>
    </div>
</div>

<!-- Priority Follow-ups, Overdue Tasks & Today's Interviews -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 text-xs">
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Today's Interviews ({{ $todaysInterviews->count() }})</h3>
        <div class="space-y-2.5">
            @forelse($todaysInterviews as $int)
                <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200/70 dark:border-slate-800">
                    <div class="font-semibold text-slate-900 dark:text-white">{{ $int->candidate?->full_name }}</div>
                    <div class="text-[11px] text-slate-500">{{ $int->round_name }} • {{ $int->interview_time }} ({{ strtoupper($int->mode) }})</div>
                </div>
            @empty
                <div class="text-slate-400 py-4">No interviews scheduled for today.</div>
            @endforelse
        </div>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Today's & Overdue Tasks ({{ $todaysFollowUps->count() + $overdueTasks->count() }})</h3>
        <div class="space-y-2.5">
            @foreach($overdueTasks as $tsk)
                <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 flex items-center justify-between">
                    <div>
                        <div class="font-semibold text-rose-800 dark:text-rose-200">[OVERDUE] {{ $tsk->title }}</div>
                        <div class="text-[10px] text-rose-600">Due: {{ $tsk->due_date->format('d M Y') }}</div>
                    </div>
                    <form method="POST" action="{{ route('tasks.complete', $tsk) }}">
                        @csrf
                        <button type="submit" class="px-2 py-1 rounded bg-emerald-600 text-white text-[10px] font-semibold">Done</button>
                    </form>
                </div>
            @endforeach
            @foreach($todaysFollowUps as $tsk)
                <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <div class="font-semibold text-slate-900 dark:text-white">{{ $tsk->title }}</div>
                        <div class="text-[10px] text-slate-500">Time: {{ $tsk->due_time ?? '11:00' }}</div>
                    </div>
                    <form method="POST" action="{{ route('tasks.complete', $tsk) }}">
                        @csrf
                        <button type="submit" class="px-2 py-1 rounded bg-emerald-600 text-white text-[10px] font-semibold">Done</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Offers & Upcoming Joinings</h3>
        <div class="space-y-2.5">
            @foreach($upcomingJoinings as $jon)
                <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800">
                    <div class="font-semibold text-emerald-900 dark:text-emerald-200">{{ $jon->candidate?->full_name }}</div>
                    <div class="text-[11px] text-emerald-700">{{ $jon->client?->company_name }} • Expected: {{ $jon->expected_joining_date?->format('d M Y') }}</div>
                </div>
            @endforeach
            @foreach($offerFollowUps as $ofr)
                <div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800">
                    <div class="font-semibold text-blue-900 dark:text-blue-200">{{ $ofr->candidate?->full_name }} — {{ $ofr->offered_designation }}</div>
                    <div class="text-[11px] text-blue-700">Status: {{ strtoupper(str_replace('_', ' ', $ofr->status)) }}</div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
