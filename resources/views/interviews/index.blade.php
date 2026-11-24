@extends('layouts.app')
@section('title', 'Interviews & Unlimited Multiple Rounds')
@section('page_title', 'Interview Scheduling & Multi-Round Progression Engine')
@section('page_subtitle', 'Schedule, reschedule, track multiple attempts, record round-by-round evaluations, and enforce stage progression')

@section('content')
<div x-data="{ showScheduleModal: false }">
    <!-- Filter & Schedule Toolbar -->
    <div class="mb-5 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
        <form method="GET" action="{{ route('interviews.index') }}" class="flex flex-wrap items-center gap-2.5">
            <input type="date" name="date" value="{{ request('date') }}" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
            <select name="status" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                <option value="">All Round Statuses</option>
                @foreach(\App\Models\InterviewRecord::STATUSES as $sk => $sl)
                    <option value="{{ $sk }}" {{ request('status') === $sk ? 'selected' : '' }}>{{ $sl }}</option>
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
        <button type="button" @click="showScheduleModal = true" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">
            + Schedule / Reschedule Interview Round
        </button>
    </div>

    <!-- Interview Rounds Timeline & Table -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                    <tr>
                        <th class="px-4 py-3">Interview ID</th>
                        <th class="px-4 py-3">Candidate & Job</th>
                        <th class="px-4 py-3">Round & Mode</th>
                        <th class="px-4 py-3">Schedule & Panel</th>
                        <th class="px-4 py-3">Status & Score</th>
                        <th class="px-4 py-3">Record Round Outcome / Override</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
                    @forelse($interviews as $int)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-mono font-bold text-[#D3003B]">
                                {{ $int->interview_code }}
                                <div class="text-[10px] text-slate-400">Attempt #{{ $int->attempts_count }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @if($int->candidate)
                                    <a href="{{ route('candidates.show', $int->candidate) }}" class="font-bold text-slate-900 dark:text-white hover:text-[#D3003B]">{{ $int->candidate->full_name }}</a>
                                @endif
                                <div class="text-[11px] text-slate-500">{{ $int->jobRequirement?->title }} ({{ $int->client?->company_name }})</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded bg-[#0B1528] text-white text-[10px] font-bold">Round #{{ $int->round_number }}</span>
                                <div class="font-semibold mt-1">{{ $int->round_name }}</div>
                                <div class="text-[10px] text-slate-400 uppercase">{{ str_replace('_', ' ', $int->mode) }} • {{ $int->duration_minutes }}m</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold">{{ $int->interview_date?->format('d M Y') }} at {{ $int->interview_time }}</div>
                                <div class="text-[11px] text-slate-500">Panel: {{ $int->interviewer_name }}</div>
                                @if($int->meeting_link)
                                    <a href="{{ $int->meeting_link }}" target="_blank" class="text-[10px] text-[#1E40AF] hover:underline">Meeting Link ↗</a>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ in_array($int->status, ['passed', 'skipped_with_authorisation']) ? 'bg-emerald-100 text-emerald-800' : ($int->status === 'failed' ? 'bg-rose-100 text-rose-800' : 'bg-blue-100 text-blue-800') }}">
                                    {{ \App\Models\InterviewRecord::STATUSES[$int->status] ?? $int->status }}
                                </span>
                                @if($int->score !== null)
                                    <div class="text-[11px] font-bold mt-1">Score: {{ $int->score }} / 10</div>
                                @endif
                                @if($int->is_overridden)
                                    <div class="text-[10px] text-amber-700 font-semibold mt-0.5">Overridden: {{ $int->override_reason }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 space-y-2">
                                <form method="POST" action="{{ route('interviews.result', $int) }}" class="flex flex-wrap items-center gap-1.5">
                                    @csrf
                                    <select name="status" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-[11px]">
                                        <option value="passed" {{ $int->status === 'passed' ? 'selected' : '' }}>Passed</option>
                                        <option value="failed" {{ $int->status === 'failed' ? 'selected' : '' }}>Failed</option>
                                        <option value="completed" {{ $int->status === 'completed' ? 'selected' : '' }}>Completed</option>
                                        <option value="on_hold" {{ $int->status === 'on_hold' ? 'selected' : '' }}>On Hold</option>
                                        <option value="no_show" {{ $int->status === 'no_show' ? 'selected' : '' }}>No Show</option>
                                        <option value="cancelled" {{ $int->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                    </select>
                                    <input type="number" step="0.5" min="0" max="10" name="score" value="{{ $int->score ?? 8 }}" placeholder="Score" class="w-16 px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-transparent text-[11px]">
                                    <input type="text" name="feedback_summary" required placeholder="Feedback / evaluation remarks..." value="{{ $int->feedback_summary }}" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-transparent text-[11px] w-40">
                                    <button type="submit" class="px-2.5 py-1 rounded bg-[#0B1528] text-white font-semibold text-[11px]">Save</button>
                                </form>

                                @if($int->status === 'failed' && !$int->is_overridden && auth()->user()->hasRole(['super_admin', 'manager']))
                                    <form method="POST" action="{{ route('interviews.override', $int) }}" class="flex items-center gap-1.5 pt-1 border-t border-slate-100 dark:border-slate-800">
                                        @csrf
                                        <input type="text" name="override_reason" required minlength="10" placeholder="Mandatory audit reason for override..." class="px-2 py-1 rounded border border-amber-300 bg-amber-50/40 text-[10px] flex-1">
                                        <button type="submit" class="px-2 py-1 rounded bg-amber-600 text-white font-semibold text-[10px]">Authorise Override</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No interview rounds scheduled yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-800">
            {{ $interviews->links() }}
        </div>
    </div>

    <!-- Schedule / Reschedule Interview Round Modal -->
    <div x-show="showScheduleModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 overflow-y-auto">
        <div @click.away="showScheduleModal = false" x-data="{ selectedAppId: '{{ $eligibleApplications->first()?->id ?? '' }}' }" class="w-full max-w-2xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 text-xs">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Schedule or Reschedule Interview Round (1..N)</h3>
                <button type="button" @click="showScheduleModal = false" class="text-slate-400">✕</button>
            </div>
            @if($eligibleApplications->isEmpty())
                <div class="py-6 text-center text-slate-400">No candidate applications currently in Shortlisted or Interview stages.</div>
            @else
                <form method="POST" :action="'/applications/' + selectedAppId + '/interviews'" class="mt-4 space-y-3.5">
                    @csrf
                    <div>
                        <label class="block font-semibold mb-1">Candidate Application *</label>
                        <select x-model="selectedAppId" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            @foreach($eligibleApplications as $ea)
                                <option value="{{ $ea->id }}">{{ $ea->application_code }} — {{ $ea->candidate?->full_name }} ({{ $ea->jobRequirement?->title }} • Current Round: {{ $ea->current_interview_round }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-semibold mb-1">Round Number (1..N) *</label>
                            <input type="number" name="round_number" value="1" min="1" max="50" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block font-semibold mb-1">Round Name</label>
                            <input type="text" name="round_name" placeholder="e.g. Round 2: System Architecture Assessment" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Interview Date *</label>
                            <input type="date" name="interview_date" value="{{ now()->addDay()->toDateString() }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Time (IST) *</label>
                            <input type="time" name="interview_time" value="15:00" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Duration (Minutes) *</label>
                            <input type="number" name="duration_minutes" value="45" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Interview Mode *</label>
                            <select name="mode" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                                @foreach(\App\Models\InterviewRecord::MODES as $mk => $ml)
                                    <option value="{{ $mk }}">{{ $ml }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Interviewer Name *</label>
                            <input type="text" name="interviewer_name" required placeholder="Panel Lead Name" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Interviewer Email</label>
                            <input type="email" name="interviewer_email" placeholder="panel@client.com" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block font-semibold mb-1">Video Meeting Link / Physical Location</label>
                            <input type="text" name="meeting_link" placeholder="https://meet.google.com/..." class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Reschedule Reason (if rescheduling)</label>
                            <input type="text" name="reschedule_reason" placeholder="Panel availability shift" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                        <button type="button" @click="showScheduleModal = false" class="px-3.5 py-2 rounded-lg border border-slate-300">Cancel</button>
                        <button type="submit" class="px-5 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">Confirm & Send Invitations</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
