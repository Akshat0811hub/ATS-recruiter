@extends('layouts.app')
@section('title', 'Team Management')
@section('page_title', 'Recruitment Team & Practice Management')
@section('page_subtitle', 'Structure recruitment squads, assign managers and team leaders, and define daily sourcing and submission targets')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 text-xs">
    <div class="lg:col-span-5">
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Create New Recruitment Team</h2>
            <form method="POST" action="{{ route('admin.teams.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block font-semibold mb-1">Team Name *</label>
                    <input type="text" name="name" required placeholder="e.g. BFSI & FinTech Practice" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Specialization</label>
                    <input type="text" name="specialization" placeholder="Banking, Insurance, Core Tech" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold mb-1">Manager</label>
                        <select name="manager_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            @foreach($managers as $m)
                                <option value="{{ $m->id }}">{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Team Leader</label>
                        <select name="team_leader_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            @foreach($teamLeaders as $tl)
                                <option value="{{ $tl->id }}">{{ $tl->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block font-semibold mb-1">Daily Sourcing</label>
                        <input type="number" name="daily_sourcing_target" value="50" required class="w-full px-2.5 py-1.5 rounded border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Daily Calls</label>
                        <input type="number" name="daily_call_target" value="150" required class="w-full px-2.5 py-1.5 rounded border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Daily Submissions</label>
                        <input type="number" name="daily_submission_target" value="20" required class="w-full px-2.5 py-1.5 rounded border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                </div>
                <div>
                    <label class="block font-semibold mb-1">Assign Recruiters</label>
                    <select name="member_ids[]" multiple class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 h-28">
                        @foreach($recruiters as $rec)
                            <option value="{{ $rec->id }}">{{ $rec->name }} ({{ $rec->employee_code }})</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="w-full py-2 rounded-lg bg-[#D3003B] text-white font-semibold">Create Team</button>
            </form>
        </div>
    </div>

    <div class="lg:col-span-7 space-y-4">
        @foreach($teams as $team)
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-mono font-bold text-[#D3003B]">{{ $team->code }}</span>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $team->name }}</h3>
                        <p class="text-slate-500">{{ $team->specialization }} • Manager: {{ $team->manager?->name ?? 'N/A' }} • TL: {{ $team->teamLeader?->name ?? 'N/A' }}</p>
                    </div>
                    <span class="px-2.5 py-1 rounded bg-slate-100 dark:bg-slate-800 font-semibold">{{ $team->job_requirements_count }} Active Jobs</span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <div class="text-[10px] font-semibold uppercase text-slate-400 mb-1.5">Team Members ({{ $team->members->count() }})</div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($team->members as $mem)
                            <span class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300 font-medium">{{ $mem->name }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
