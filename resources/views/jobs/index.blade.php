@extends('layouts.app')
@section('title', 'Jobs')
@section('page_title', 'Jobs')
@section('page_subtitle', 'Manage open roles, assign recruiters and track remaining vacancies.')

@section('content')
<div x-data="{
    showCreateModal: {{ request()->boolean('create') || $errors->any() ? 'true' : 'false' }},
    rounds: [
        { name: 'Round 1: HR Screening', mode: 'telephonic' },
        { name: 'Round 2: Technical Interview', mode: 'video' },
        { name: 'Round 3: Technical Assessment', mode: 'technical_assessment' },
        { name: 'Round 4: Management Interview', mode: 'video' },
        { name: 'Round 5: Final HR Discussion', mode: 'face_to_face' }
    ]
}">
    <!-- Filter & Action Toolbar -->
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 text-xs">
        <form method="GET" action="{{ route('jobs.index') }}" class="flex flex-wrap items-center gap-2.5">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search Job ID, title, client, location..."
                   class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent w-64">
            <select name="client_id" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                <option value="">All Clients</option>
                @foreach($clients as $cl)
                    <option value="{{ $cl->id }}" {{ request('client_id') == $cl->id ? 'selected' : '' }}>{{ $cl->company_name }}</option>
                @endforeach
            </select>
            <select name="priority" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                <option value="">All Priorities</option>
                <option value="critical" {{ request('priority') === 'critical' ? 'selected' : '' }}>Critical</option>
                <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>High</option>
                <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Medium</option>
                <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
            </select>
            <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-[#0B1528] text-white font-semibold">Filter</button>
        </form>
        @if(auth()->user()->hasRole(['super_admin', 'manager', 'team_leader']))
            <button type="button" @click="showCreateModal = true" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">
                + Create Job Requirement
            </button>
        @endif
    </div>

    <!-- Jobs Table -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                    <tr>
                        <th class="px-4 py-3">Job ID</th>
                        <th class="px-4 py-3">Title & Client</th>
                        <th class="px-4 py-3">Location & Mode</th>
                        <th class="px-4 py-3 text-center">Vacancies (Req / Joined / Rem)</th>
                        <th class="px-4 py-3">Assigned Recruiters</th>
                        <th class="px-4 py-3 text-right">Rounds</th>
                        <th class="px-4 py-3">Priority & Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
                    @forelse($jobs as $job)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-mono font-bold text-[#D3003B]">
                                <a href="{{ route('jobs.show', $job) }}">{{ $job->job_code }}</a>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('jobs.show', $job) }}" class="font-bold text-slate-900 dark:text-white hover:text-[#D3003B]">{{ $job->title }}</a>
                                <div class="text-[11px] text-slate-500">{{ $job->client?->company_name }} • {{ $job->min_experience }}–{{ $job->max_experience ?? '15+' }} Yrs</div>
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ $job->location }}</div>
                                <div class="text-[11px] text-slate-500 uppercase">{{ $job->work_mode }} • {{ str_replace('_', ' ', $job->employment_type) }}</div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 font-semibold">
                                    {{ $job->openings_count }} Req / <span class="text-emerald-600">{{ $job->joined_count }} Joined</span> / <span class="text-[#1E40AF] font-bold">{{ $job->remaining_count }} Open</span>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @forelse($job->assignments as $asg)
                                    <span class="inline-block px-2 py-0.5 mr-1 mb-1 rounded text-[10px] font-medium {{ $asg->is_primary ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $asg->recruiter?->name }}
                                    </span>
                                @empty
                                    <span class="text-slate-400">Unassigned</span>
                                @endforelse
                            </td>
                            <td class="px-4 py-3 text-right font-semibold">{{ $job->interview_rounds_count }} Rounds</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $job->priority === 'critical' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800' }}">{{ $job->priority }}</span>
                                <div class="text-[10px] text-slate-500 uppercase mt-1">{{ str_replace('_', ' ', $job->status) }}</div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('jobs.show', $job) }}" class="text-[#1E40AF] font-semibold hover:underline">Manage</a>
                                    @if(auth()->user()->hasRole(['super_admin', 'manager', 'team_leader']))
                                        <form method="POST" action="{{ route('jobs.duplicate', $job) }}">
                                            @csrf
                                            <button type="submit" class="text-slate-500 hover:text-slate-800" title="Duplicate Requirement">Clone</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">No job requirements found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-800">
            {{ $jobs->links() }}
        </div>
    </div>

    <!-- Create Job Requirement Modal -->
    @if(auth()->user()->hasRole(['super_admin', 'manager', 'team_leader']))
    <div x-show="showCreateModal" x-cloak role="dialog" aria-modal="true" aria-label="Add job" @keydown.escape.window="showCreateModal = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 overflow-y-auto">
        <div @click.away="showCreateModal = false" class="w-full max-w-4xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Create Job Requirement & Configurable Interview Workflow</h3>
                <button type="button" @click="showCreateModal = false" class="text-xs text-slate-400">✕</button>
            </div>
            <form method="POST" action="{{ route('jobs.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4 text-xs">
                @csrf
                @if($errors->any())
                    <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-rose-800">
                        <p class="font-semibold">Please check the job requirement details below:</p>
                        <ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <div>
                        <label class="block font-semibold mb-1">Client Organisation *</label>
                        <select name="client_id" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            @foreach($clients as $cl)
                                <option value="{{ $cl->id }}">{{ $cl->company_name }} ({{ $cl->client_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block font-semibold mb-1">Job Title *</label>
                        <input type="text" name="title" required placeholder="e.g. Senior Backend Architect" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Number of Openings *</label>
                        <input type="number" name="openings_count" value="2" min="1" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Location *</label>
                        <input type="text" name="location" value="Mumbai" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Country *</label>
                        <input type="text" name="country" value="India" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Work Mode</label>
                        <select name="work_mode" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            <option value="onsite">Onsite</option>
                            <option value="hybrid">Hybrid</option>
                            <option value="remote">Remote</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Employment Type</label>
                        <select name="employment_type" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            <option value="full_time">Full Time</option>
                            <option value="contract">Contract</option>
                            <option value="consultant">Consultant</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Hiring Priority</label>
                        <select name="priority" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            <option value="critical">Critical</option>
                            <option value="high" selected>High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Min Experience (Yrs)</label>
                        <input type="number" step="0.5" name="min_experience" value="3" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Max Experience (Yrs)</label>
                        <input type="number" step="0.5" name="max_experience" value="8" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Currency</label>
                        <input type="text" name="currency" value="INR" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Min Annual Salary</label>
                        <input type="number" name="min_salary" value="1500000" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Max Annual Salary</label>
                        <input type="number" name="max_salary" value="2800000" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Expected Closure Date</label>
                        <input type="date" name="expected_closure_date" value="{{ now()->addDays(21)->toDateString() }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block font-semibold mb-1">Required Skills (Comma separated)</label>
                        <input type="text" name="required_skills_csv" placeholder="PHP, Laravel, MySQL, System Design" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Private JD Attachment (PDF/DOCX)</label>
                        <input type="file" name="jd_file" class="w-full text-xs">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold mb-1">Job Description & Responsibilities</label>
                    <textarea name="description" rows="2" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent"></textarea>
                </div>

                <!-- Unlimited Configurable Interview Rounds -->
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <div class="font-bold text-slate-900 dark:text-white">Unlimited Configurable Interview Rounds</div>
                            <div class="text-[11px] text-slate-500">Define as many interview rounds (1..N) as the client requires.</div>
                        </div>
                        <button type="button" @click="rounds.push({ name: 'Round ' + (rounds.length + 1) + ': Custom Stage', mode: 'video' })"
                                class="px-2.5 py-1 rounded bg-[#0B1528] text-white font-semibold text-[11px]">
                            + Add Round
                        </button>
                    </div>
                    <div class="space-y-2">
                        <template x-for="(rnd, index) in rounds" :key="index">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-[11px] w-14 text-slate-500" x-text="'#' + (index + 1)"></span>
                                <input type="text" :name="'interview_rounds[' + index + '][name]'" x-model="rnd.name" required class="flex-1 px-2.5 py-1.5 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                                <select :name="'interview_rounds[' + index + '][mode]'" x-model="rnd.mode" class="px-2.5 py-1.5 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                                    <option value="telephonic">Telephonic</option>
                                    <option value="video">Video</option>
                                    <option value="face_to_face">Face-to-face</option>
                                    <option value="technical_assessment">Technical Assessment</option>
                                    <option value="online_assessment">Online Assessment</option>
                                </select>
                                <button type="button" @click="rounds.length > 1 && rounds.splice(index, 1)" class="text-rose-600 px-2">✕</button>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="flex justify-end pt-3 border-t border-slate-200 dark:border-slate-800">
                    <button type="submit" class="px-5 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">Save Job Requirement</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
