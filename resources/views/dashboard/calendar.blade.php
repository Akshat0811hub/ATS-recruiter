@extends('layouts.app')
@section('title', 'Recruitment Calendar & Agenda')
@section('page_title', 'Recruitment Calendar & Daily Operational Agenda')
@section('page_subtitle', 'Scheduled interviews, candidate follow-ups, and upcoming joining dates')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 text-xs">
    <!-- Scheduled Interviews -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Interview Schedule ({{ $interviews->count() }})</h2>
            <a href="{{ route('interviews.index') }}" class="text-[#1E40AF] hover:underline">Manage →</a>
        </div>
        <div class="space-y-3 max-h-[600px] overflow-y-auto">
            @forelse($interviews as $int)
                <div class="p-3.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-[10px] text-[#D3003B] font-bold">{{ $int->interview_code }}</span>
                        <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800 text-[10px] font-semibold uppercase">{{ $int->status }}</span>
                    </div>
                    <div class="font-semibold text-slate-900 dark:text-white mt-1">{{ $int->candidate?->full_name }}</div>
                    <div class="text-slate-500">{{ $int->round_name }} • {{ $int->jobRequirement?->title }}</div>
                    <div class="mt-2 pt-2 border-t border-slate-200/70 dark:border-slate-700 flex items-center justify-between text-[11px] tabular-nums">
                        <span>📅 {{ $int->interview_date?->format('d M Y') }} at {{ $int->interview_time }}</span>
                        <span>{{ strtoupper($int->mode) }}</span>
                    </div>
                </div>
            @empty
                <div class="py-8 text-center text-slate-400">No scheduled interviews.</div>
            @endforelse
        </div>
    </div>

    <!-- Pending Tasks & Follow-ups -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Pending Follow-ups ({{ $tasks->count() }})</h2>
            <a href="{{ route('tasks.index') }}" class="text-[#1E40AF] hover:underline">All Tasks →</a>
        </div>
        <div class="space-y-3 max-h-[600px] overflow-y-auto">
            @forelse($tasks as $task)
                <div class="p-3.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 flex items-start justify-between gap-2">
                    <div>
                        <div class="font-semibold text-slate-900 dark:text-white">{{ $task->title }}</div>
                        <div class="text-[11px] text-slate-500 mt-0.5">Due: {{ $task->due_date?->format('d M Y') }} • {{ $task->assignee?->name }}</div>
                    </div>
                    <form method="POST" action="{{ route('tasks.complete', $task) }}">
                        @csrf
                        <button type="submit" class="px-2 py-1 rounded bg-emerald-600 text-white text-[10px] font-semibold">Complete</button>
                    </form>
                </div>
            @empty
                <div class="py-8 text-center text-slate-400">No pending follow-ups.</div>
            @endforelse
        </div>
    </div>

    <!-- Upcoming Candidate Joinings -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Upcoming Joinings ({{ $joinings->count() }})</h2>
            <a href="{{ route('joinings.index') }}" class="text-[#1E40AF] hover:underline">Manage →</a>
        </div>
        <div class="space-y-3 max-h-[600px] overflow-y-auto">
            @forelse($joinings as $jon)
                <div class="p-3.5 rounded-lg bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800">
                    <div class="font-mono text-[10px] text-emerald-700 font-bold">{{ $jon->joining_code }}</div>
                    <div class="font-semibold text-slate-900 dark:text-white mt-0.5">{{ $jon->candidate?->full_name }}</div>
                    <div class="text-slate-600 dark:text-slate-400">{{ $jon->jobRequirement?->title }} ({{ $jon->client?->company_name }})</div>
                    <div class="mt-2 text-[11px] font-semibold text-emerald-800 dark:text-emerald-300 tabular-nums">Expected Joining: {{ $jon->expected_joining_date?->format('d M Y') }}</div>
                </div>
            @empty
                <div class="py-8 text-center text-slate-400">No upcoming pending joinings.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
