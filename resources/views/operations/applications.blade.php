@extends('layouts.app')
@section('title', 'Applications')
@section('page_title', 'Applications')
@section('page_subtitle', 'Each application connects one candidate to one job.')

@section('content')
<!-- Filters -->
<form method="GET" action="{{ route('applications.index') }}" class="mb-5 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 flex flex-wrap items-center gap-2.5 text-xs">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search App ID, candidate, job..." class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent w-56">
    <select name="stage" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
        <option value="">All Stages</option>
        @foreach(\App\Models\CandidateApplication::allStages() as $sk => $sl)
            <option value="{{ $sk }}" {{ request('stage') === $sk ? 'selected' : '' }}>{{ $sl }}</option>
        @endforeach
    </select>
    <select name="client_id" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
        <option value="">All Clients</option>
        @foreach($clients as $cl)
            <option value="{{ $cl->id }}" {{ request('client_id') == $cl->id ? 'selected' : '' }}>{{ $cl->company_name }}</option>
        @endforeach
    </select>
    <select name="job_id" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
        <option value="">All Jobs</option>
        @foreach($jobs as $jb)
            <option value="{{ $jb->id }}" {{ request('job_id') == $jb->id ? 'selected' : '' }}>{{ $jb->job_code }} — {{ \Illuminate\Support\Str::limit($jb->title, 28) }}</option>
        @endforeach
    </select>
    <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-[#0B1528] text-white font-semibold">Filter</button>
    <a href="{{ route('pipeline.index') }}" class="ml-auto px-3.5 py-1.5 rounded-lg bg-[#D3003B] text-white font-semibold">Open Kanban Board →</a>
</form>

<!-- Applications Table -->
<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                <tr>
                    <th class="px-4 py-3">App Code</th>
                    <th class="px-4 py-3">Candidate</th>
                    <th class="px-4 py-3">Job & Client</th>
                    <th class="px-4 py-3">Recruiter</th>
                    <th class="px-4 py-3">Current Stage</th>
                    <th class="px-4 py-3">Transition Stage</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
                @forelse($applications as $app)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono font-bold text-[#D3003B]">{{ $app->application_code }}</td>
                        <td class="px-4 py-3">
                            @if($app->candidate)
                                <a href="{{ route('candidates.show', $app->candidate) }}" class="font-bold text-slate-900 dark:text-white hover:text-[#D3003B]">{{ $app->candidate->full_name }}</a>
                                <div class="text-[11px] text-slate-500">{{ $app->candidate->mobile_number }} • {{ $app->candidate->total_experience }} Yrs</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($app->jobRequirement)
                                <a href="{{ route('jobs.show', $app->jobRequirement) }}" class="font-semibold text-slate-900 dark:text-white hover:text-[#D3003B]">{{ $app->jobRequirement->title }}</a>
                                <div class="text-[11px] text-slate-500">{{ $app->jobRequirement->job_code }} • {{ $app->jobRequirement->client?->company_name }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $app->recruiter?->name ?? 'Unassigned' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2.5 py-1 rounded-full bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300 font-bold text-[10px] uppercase">{{ $app->stageLabel() }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('applications.transition', $app) }}" class="flex items-center gap-1.5">
                                @csrf
                                <input type="hidden" name="lock_version" value="{{ $app->lock_version }}">
                                <select name="target_stage" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-[11px]">
                                    @foreach(\App\Models\CandidateApplication::allStages() as $sk => $sl)
                                        <option value="{{ $sk }}" {{ $app->pipeline_stage === $sk ? 'selected' : '' }}>{{ $sl }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="px-2 py-1 rounded bg-[#0B1528] text-white font-semibold text-[11px]">Update</button>
                            </form>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if($app->candidate)
                                <button type="button" @click="openCandidatePreview({{ $app->candidate->id }})" class="text-[#1E40AF] font-semibold hover:underline">360° Preview</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No candidate applications found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-slate-100 dark:border-slate-800">
        {{ $applications->links() }}
    </div>
</div>
@endsection
