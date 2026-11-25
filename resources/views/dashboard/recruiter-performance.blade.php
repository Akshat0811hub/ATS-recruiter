@extends('layouts.app')
@section('title', 'Recruiter performance')
@section('page_title', 'Recruiter performance')
@section('page_subtitle', 'Review sourcing, submissions and joining results across your team.')

@section('content')
<div class="space-y-6">
    <!-- Team Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
        @foreach($teams as $team)
            <div class="p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <div class="flex items-center justify-between">
                    <span class="font-mono text-[#D3003B] font-bold">{{ $team->code }}</span>
                    <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-semibold">{{ $team->recruiters_count }} Members</span>
                </div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mt-1">{{ $team->name }}</h3>
                <div class="mt-2 text-slate-500">Manager: {{ $team->manager?->name ?? 'N/A' }} • TL: {{ $team->teamLeader?->name ?? 'N/A' }}</div>
                <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-between tabular-nums">
                    <span>Assigned Jobs: <strong>{{ $team->job_requirements_count }}</strong></span>
                    <span>Daily Target: <strong>{{ $team->daily_submission_target }} subs/day</strong></span>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Recruiter Performance Table -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
        <div class="p-4 border-b border-slate-200 dark:border-slate-800">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Individual Recruiter Activity & Placement Scorecard</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                    <tr>
                        <th class="px-4 py-3">Employee Code</th>
                        <th class="px-4 py-3">Recruiter Name</th>
                        <th class="px-4 py-3 text-right">Daily Target (Source/Call/Sub)</th>
                        <th class="px-4 py-3 text-right">Candidates Sourced</th>
                        <th class="px-4 py-3 text-right">Calls Recorded</th>
                        <th class="px-4 py-3 text-right">Profiles Submitted</th>
                        <th class="px-4 py-3 text-right">Active Pipeline</th>
                        <th class="px-4 py-3 text-right">Confirmed Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
                    @foreach($recruiters as $rec)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-mono text-[#D3003B]">{{ $rec->employee_code }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $rec->name }}</td>
                            <td class="px-4 py-3 text-right text-slate-500">{{ $rec->daily_sourcing_target }} / {{ $rec->daily_call_target }} / {{ $rec->daily_submission_target }}</td>
                            <td class="px-4 py-3 text-right">{{ $rec->sourced_candidates_count }}</td>
                            <td class="px-4 py-3 text-right">{{ $rec->call_logs_count }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ $rec->client_submissions_count }}</td>
                            <td class="px-4 py-3 text-right">{{ $rec->active_applications_count }}</td>
                            <td class="px-4 py-3 text-right font-bold text-emerald-600">{{ $rec->joined_applications_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
