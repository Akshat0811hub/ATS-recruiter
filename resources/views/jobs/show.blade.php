@extends('layouts.app')
@section('title', $job->title . ' (' . $job->job_code . ')')
@section('page_title', $job->title . ' — ' . $job->job_code)
@section('page_subtitle', ($job->client?->company_name ?? '') . ' • ' . $job->location . ' (' . strtoupper($job->work_mode) . ')')

@section('content')
<!-- Vacancy Level Counter Bar -->
<div class="grid grid-cols-2 sm:grid-cols-6 gap-3 mb-6 text-xs">
    <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="text-[10px] text-slate-400 uppercase font-semibold">Positions Required</div>
        <div class="text-lg font-bold tabular-nums mt-0.5">{{ $job->openings_count }}</div>
    </div>
    <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="text-[10px] text-slate-400 uppercase font-semibold">Confirmed Joined</div>
        <div class="text-lg font-bold tabular-nums text-emerald-600 mt-0.5">{{ $job->joined_count }}</div>
    </div>
    <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="text-[10px] text-slate-400 uppercase font-semibold">Remaining Open</div>
        <div class="text-lg font-bold tabular-nums text-[#D3003B] mt-0.5">{{ $job->remaining_count }}</div>
    </div>
    <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="text-[10px] text-slate-400 uppercase font-semibold">Interview Rounds</div>
        <div class="text-lg font-bold tabular-nums mt-0.5">{{ $job->interviewRoundDefinitions->count() }} Stages</div>
    </div>
    <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="text-[10px] text-slate-400 uppercase font-semibold">Profiles Submitted</div>
        <div class="text-lg font-bold tabular-nums mt-0.5">{{ $job->submissions->count() }}</div>
    </div>
    <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="text-[10px] text-slate-400 uppercase font-semibold">Requirement Status</div>
        <div class="text-xs font-bold uppercase text-[#1E40AF] mt-1">{{ str_replace('_', ' ', $job->status) }}</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 text-xs">
    <!-- Left Column: Job Spec, Recruiter Assignment & Interview Workflow -->
    <div class="lg:col-span-5 space-y-6">
        <!-- Recruiter Assignment & Workload Module -->
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Assigned Recruiters ({{ $job->assignments->count() }})</h2>
            <div class="space-y-2 mb-4">
                @forelse($job->assignments as $asg)
                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                        <div>
                            <div class="font-semibold text-slate-900 dark:text-white">{{ $asg->recruiter?->name }}</div>
                            <div class="text-[11px] text-slate-500">Daily Target: {{ $asg->daily_sourcing_target }} sourced / {{ $asg->submission_target }} submissions</div>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $asg->is_primary ? 'bg-[#D3003B] text-white' : 'bg-blue-100 text-blue-800' }}">
                            {{ $asg->is_primary ? 'Primary' : 'Secondary' }}
                        </span>
                    </div>
                @empty
                    <div class="text-slate-400">No recruiters assigned yet.</div>
                @endforelse
            </div>

            @if(auth()->user()->hasRole(['super_admin', 'manager', 'team_leader']))
                <form method="POST" action="{{ route('jobs.assign', $job) }}" class="pt-3 border-t border-slate-200 dark:border-slate-800 space-y-3">
                    @csrf
                    <div class="font-semibold text-slate-800 dark:text-slate-200">Assign Primary & Secondary Recruiters</div>
                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="block text-[11px] mb-1">Primary Recruiter *</label>
                            <select name="primary_recruiter_id" required class="w-full px-2.5 py-1.5 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                                @foreach($recruiters as $rec)
                                    <option value="{{ $rec->id }}">{{ $rec->name }} ({{ $rec->job_assignments_count }} jobs)</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] mb-1">Secondary Recruiters</label>
                            <select name="secondary_recruiter_ids[]" multiple class="w-full px-2.5 py-1.5 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 h-16">
                                @foreach($recruiters as $rec)
                                    <option value="{{ $rec->id }}">{{ $rec->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] mb-1">Daily Sourcing Target</label>
                            <input type="number" name="daily_sourcing_target" value="6" min="1" required class="w-full px-2.5 py-1.5 rounded border border-slate-300 dark:border-slate-700 bg-transparent">
                        </div>
                        <div>
                            <label class="block text-[11px] mb-1">Submission Target</label>
                            <input type="number" name="submission_target" value="5" min="1" required class="w-full px-2.5 py-1.5 rounded border border-slate-300 dark:border-slate-700 bg-transparent">
                        </div>
                    </div>
                    <input type="hidden" name="priority" value="{{ $job->priority }}">
                    <button type="submit" class="w-full py-2 rounded-lg bg-[#0B1528] text-white font-semibold">Assign & Notify Recruiters</button>
                </form>
            @endif
        </div>

        <!-- Configurable Unlimited Interview Rounds -->
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Configured Interview Workflow ({{ $job->interviewRoundDefinitions->count() }} Rounds)</h2>
            <div class="space-y-2 mb-4">
                @foreach($job->interviewRoundDefinitions as $rDef)
                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                        <span class="font-medium">{{ $rDef->round_name }}</span>
                        <span class="text-[10px] uppercase px-2 py-0.5 rounded bg-slate-200 dark:bg-slate-700">{{ str_replace('_', ' ', $rDef->interview_mode) }}</span>
                    </div>
                @endforeach
            </div>
            @if(auth()->user()->hasRole(['super_admin', 'manager', 'team_leader']))
                <form method="POST" action="{{ route('jobs.interview-rounds.store', $job) }}" class="pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center gap-2">
                    @csrf
                    <input type="text" name="round_name" required placeholder="e.g. Round {{ $job->interviewRoundDefinitions->count() + 1 }}: Culture & Leadership" class="flex-1 px-2.5 py-1.5 rounded border border-slate-300 dark:border-slate-700 bg-transparent">
                    <input type="hidden" name="interview_mode" value="video">
                    <input type="hidden" name="default_duration_minutes" value="45">
                    <button type="submit" class="px-3 py-1.5 rounded bg-[#D3003B] text-white font-semibold">+ Add Round</button>
                </form>
            @endif
        </div>
    </div>

    <!-- Right Column: Candidate Applications for this Job -->
    <div class="lg:col-span-7 space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Candidates Associated with {{ $job->job_code }} ({{ $job->applications->count() }})</h2>
                <div class="flex items-center gap-2">
                    @if($job->jd_storage_id)
                        <a href="{{ route('documents.download', $job->jd_storage_id) }}" class="px-2.5 py-1 rounded bg-slate-100 dark:bg-slate-800 font-semibold">Download JD</a>
                    @endif
                    <a href="{{ route('pipeline.index', ['job_id' => $job->id]) }}" class="px-2.5 py-1 rounded bg-[#D3003B] text-white font-semibold">Job Kanban →</a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                        <tr>
                            <th class="px-4 py-2.5">App ID</th>
                            <th class="px-4 py-2.5">Candidate</th>
                            <th class="px-4 py-2.5">Recruiter Owner</th>
                            <th class="px-4 py-2.5">Stage</th>
                            <th class="px-4 py-2.5 text-right">Expected CTC</th>
                            <th class="px-4 py-2.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
                        @forelse($job->applications as $app)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-mono text-[#D3003B]">{{ $app->application_code }}</td>
                                <td class="px-4 py-3">
                                    @if($app->candidate)
                                        <a href="{{ route('candidates.show', $app->candidate) }}" class="font-bold text-slate-900 dark:text-white hover:text-[#D3003B]">{{ $app->candidate->full_name }}</a>
                                        <div class="text-[11px] text-slate-500">{{ $app->candidate->mobile_number }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $app->recruiter?->name ?? 'Unassigned' }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300 font-semibold text-[10px]">{{ $app->stageLabel() }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format((float)$app->expected_ctc) }} {{ $app->currency }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if($app->candidate)
                                        <button type="button" @click="openCandidatePreview({{ $app->candidate->id }})" class="text-[#1E40AF] font-semibold hover:underline">360° Preview</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No candidates associated with this job requirement yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
