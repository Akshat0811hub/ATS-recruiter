@extends('layouts.app')
@section('title', 'Recruitment Pipeline Kanban')
@section('page_title', 'Interactive Recruitment Pipeline (Kanban & Table View)')
@section('page_subtitle', 'Sourced → Contacted → Interested → Screened → Submitted → Shortlisted → Interview → Selected → Offer → Joined')

@section('content')
<div x-data="{
    draggingAppId: null,
    draggingLockVersion: null,
    handleDragStart(appId, lockVer) {
        this.draggingAppId = appId;
        this.draggingLockVersion = lockVer;
    },
    async handleDrop(targetStage) {
        if (!this.draggingAppId) return;
        const form = document.getElementById('transition-form-' + this.draggingAppId);
        if (form) {
            form.querySelector('select[name=target_stage]').value = targetStage;
            form.submit();
        }
        this.draggingAppId = null;
    }
}">
    <!-- Filter & View Toggle Toolbar -->
    <div class="mb-5 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
        <form method="GET" action="{{ route('pipeline.index') }}" class="flex flex-wrap items-center gap-2.5">
            <input type="hidden" name="view" value="{{ $viewMode }}">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search candidate name or code..." class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent w-56">
            <select name="client_id" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                <option value="">All Clients</option>
                @foreach($clients as $cl)
                    <option value="{{ $cl->id }}" {{ request('client_id') == $cl->id ? 'selected' : '' }}>{{ $cl->company_name }}</option>
                @endforeach
            </select>
            <select name="job_id" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                <option value="">All Job Requirements</option>
                @foreach($jobs as $jb)
                    <option value="{{ $jb->id }}" {{ request('job_id') == $jb->id ? 'selected' : '' }}>{{ $jb->job_code }} — {{ \Illuminate\Support\Str::limit($jb->title, 28) }}</option>
                @endforeach
            </select>
            <select name="recruiter_id" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                <option value="">All Recruiters</option>
                @foreach($recruiters as $rec)
                    <option value="{{ $rec->id }}" {{ request('recruiter_id') == $rec->id ? 'selected' : '' }}>{{ $rec->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-[#0B1528] text-white font-semibold">Filter</button>
        </form>

        <div class="inline-flex rounded-lg border border-slate-300 dark:border-slate-700 overflow-hidden">
            <a href="{{ route('pipeline.index', array_merge(request()->query(), ['view' => 'kanban'])) }}"
               class="px-3 py-1.5 font-semibold {{ $viewMode === 'kanban' ? 'bg-[#0B1528] text-white' : 'bg-white dark:bg-slate-900 text-slate-600' }}">
                Kanban Board
            </a>
            <a href="{{ route('pipeline.index', array_merge(request()->query(), ['view' => 'table'])) }}"
               class="px-3 py-1.5 font-semibold {{ $viewMode === 'table' ? 'bg-[#0B1528] text-white' : 'bg-white dark:bg-slate-900 text-slate-600' }}">
                Table View
            </a>
        </div>
    </div>

    @if($viewMode === 'table')
        <!-- Table View -->
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                    <tr>
                        <th class="px-4 py-3">App Code</th>
                        <th class="px-4 py-3">Candidate</th>
                        <th class="px-4 py-3">Job & Client</th>
                        <th class="px-4 py-3">Recruiter</th>
                        <th class="px-4 py-3">Stage</th>
                        <th class="px-4 py-3">Change Stage</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($applications as $app)
                        <tr>
                            <td class="px-4 py-3 font-mono font-bold text-[#D3003B]">{{ $app->application_code }}</td>
                            <td class="px-4 py-3 font-semibold">{{ $app->candidate?->full_name }}</td>
                            <td class="px-4 py-3">{{ $app->jobRequirement?->title }} ({{ $app->jobRequirement?->client?->company_name }})</td>
                            <td class="px-4 py-3">{{ $app->recruiter?->name }}</td>
                            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800 font-bold text-[10px]">{{ $app->stageLabel() }}</span></td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('applications.transition', $app) }}" class="flex items-center gap-1.5">
                                    @csrf
                                    <input type="hidden" name="lock_version" value="{{ $app->lock_version }}">
                                    <select name="target_stage" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-[11px]">
                                        @foreach(\App\Models\CandidateApplication::allStages() as $sk => $sl)
                                            <option value="{{ $sk }}" {{ $app->pipeline_stage === $sk ? 'selected' : '' }}>{{ $sl }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="px-2 py-1 rounded bg-[#D3003B] text-white font-semibold text-[10px]">Move</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <!-- Visual Kanban Board (13 Main Lifecycle Stages + Alternative States) -->
        <div class="flex gap-3.5 overflow-x-auto pb-6">
            @foreach(array_merge($mainStages, $alternativeStates) as $stageKey => $stageLabel)
                @php
                    $stageApps = $groupedByStage->get($stageKey, collect());
                    $isAlt = array_key_exists($stageKey, $alternativeStates);
                @endphp
                <div class="w-72 flex-shrink-0 rounded-xl {{ $isAlt ? 'bg-rose-50/40 dark:bg-rose-950/20 border-rose-200/70' : 'bg-slate-100/80 dark:bg-slate-900/80 border-slate-200/80' }} dark:border-slate-800 border p-3 flex flex-col max-h-[75vh]"
                     @dragover.prevent
                     @drop.prevent="handleDrop('{{ $stageKey }}')">
                    <div class="flex items-center justify-between mb-3 px-1">
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $stageLabel }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold tabular-nums {{ $isAlt ? 'bg-rose-200 text-rose-900' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 shadow-2xs' }}">
                            {{ $stageApps->count() }}
                        </span>
                    </div>

                    <div class="space-y-2.5 overflow-y-auto flex-1 pr-0.5 text-xs">
                        @foreach($stageApps as $app)
                            <div draggable="true"
                                 @dragstart="handleDragStart({{ $app->id }}, {{ $app->lock_version }})"
                                 class="p-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-2xs hover:border-[#D3003B] cursor-grab transition space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-[10px] text-[#D3003B] font-bold">{{ $app->application_code }}</span>
                                    <button type="button" @click="openCandidatePreview({{ $app->candidate_id }})" class="text-[10px] text-[#1E40AF] font-semibold hover:underline">360° Preview</button>
                                </div>
                                <div>
                                    @if($app->candidate)
                                        <a href="{{ route('candidates.show', $app->candidate) }}" class="font-bold text-slate-900 dark:text-white hover:text-[#D3003B] block truncate">{{ $app->candidate->full_name }}</a>
                                    @endif
                                    <div class="text-[11px] text-slate-500 truncate">{{ $app->jobRequirement?->title }}</div>
                                    <div class="text-[10px] text-slate-400 truncate">{{ $app->jobRequirement?->client?->company_name }}</div>
                                </div>
                                <div class="flex items-center justify-between text-[10px] text-slate-500 pt-1 border-t border-slate-100 dark:border-slate-800 tabular-nums">
                                    <span>{{ $app->recruiter?->name ?? 'Unassigned' }}</span>
                                    <span>{{ $app->notice_period_days ?? 30 }}d notice</span>
                                </div>
                                <form id="transition-form-{{ $app->id }}" method="POST" action="{{ route('applications.transition', $app) }}" class="pt-1 flex items-center gap-1">
                                    @csrf
                                    <input type="hidden" name="lock_version" value="{{ $app->lock_version }}">
                                    <select name="target_stage" onchange="this.form.submit()" class="w-full px-1.5 py-1 rounded border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-[10px]">
                                        @foreach(\App\Models\CandidateApplication::allStages() as $sk => $sl)
                                            <option value="{{ $sk }}" {{ $app->pipeline_stage === $sk ? 'selected' : '' }}>Move → {{ $sl }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
