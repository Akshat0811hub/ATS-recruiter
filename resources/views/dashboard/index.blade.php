@extends('layouts.app')
@section('title', 'Executive Overview Dashboard')
@section('page_title', 'Executive Overview & Live Recruitment Intelligence')
@section('page_subtitle', 'Real-time operational metrics, conversion funnel, and workload distribution from MySQL/SQLite')

@section('content')
@php
    $ov = $analytics['overview'];
    $conv = $analytics['conversions'];
    $formulas = $analytics['formulas'];
@endphp

<!-- Multi-dimension Filter Bar -->
<form method="GET" action="{{ route('dashboard') }}" class="mb-6 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 flex flex-wrap items-end gap-3 text-xs">
    <div>
        <label class="block text-[10px] font-semibold uppercase text-slate-400 mb-1">Time Period</label>
        <select name="period" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
            <option value="all" {{ ($filters['period'] ?? 'all') === 'all' ? 'selected' : '' }}>All Time</option>
            <option value="today" {{ ($filters['period'] ?? '') === 'today' ? 'selected' : '' }}>Today</option>
            <option value="yesterday" {{ ($filters['period'] ?? '') === 'yesterday' ? 'selected' : '' }}>Yesterday</option>
            <option value="this_week" {{ ($filters['period'] ?? '') === 'this_week' ? 'selected' : '' }}>This Week</option>
            <option value="this_month" {{ ($filters['period'] ?? '') === 'this_month' ? 'selected' : '' }}>This Month</option>
            <option value="custom" {{ ($filters['period'] ?? '') === 'custom' ? 'selected' : '' }}>Custom Range</option>
        </select>
    </div>
    <div>
        <label class="block text-[10px] font-semibold uppercase text-slate-400 mb-1">Client</label>
        <select name="client_id" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
            <option value="">All Clients</option>
            @foreach($clients as $cl)
                <option value="{{ $cl->id }}" {{ ($filters['client_id'] ?? '') == $cl->id ? 'selected' : '' }}>{{ $cl->company_name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-[10px] font-semibold uppercase text-slate-400 mb-1">Team</label>
        <select name="team_id" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
            <option value="">All Teams</option>
            @foreach($teams as $tm)
                <option value="{{ $tm->id }}" {{ ($filters['team_id'] ?? '') == $tm->id ? 'selected' : '' }}>{{ $tm->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-[10px] font-semibold uppercase text-slate-400 mb-1">Recruiter</label>
        <select name="recruiter_id" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
            <option value="">All Recruiters</option>
            @foreach($recruiters as $rec)
                <option value="{{ $rec->id }}" {{ ($filters['recruiter_id'] ?? '') == $rec->id ? 'selected' : '' }}>{{ $rec->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-[10px] font-semibold uppercase text-slate-400 mb-1">Job Requirement</label>
        <select name="job_id" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
            <option value="">All Jobs</option>
            @foreach($jobs as $jb)
                <option value="{{ $jb->id }}" {{ ($filters['job_id'] ?? '') == $jb->id ? 'selected' : '' }}>{{ $jb->job_code }} — {{ \Illuminate\Support\Str::limit($jb->title, 26) }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="px-4 py-1.5 rounded-lg bg-[#0B1528] text-white font-semibold hover:bg-slate-800">Apply Filters</button>
    <a href="{{ route('dashboard') }}" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300">Reset</a>
</form>

<!-- Clickable Primary KPI Grid (18 Executive Metrics) -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5 mb-6">
    <a href="{{ route('clients.index') }}" class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 hover:border-[#D3003B] transition">
        <div class="text-[11px] font-medium text-slate-500">Active / Total Clients</div>
        <div class="mt-1.5 text-xl font-bold text-slate-900 dark:text-white tabular-nums">{{ $ov['active_clients'] }} <span class="text-xs font-normal text-slate-400">/ {{ $ov['total_clients'] }}</span></div>
    </a>
    <a href="{{ route('jobs.index') }}" class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 hover:border-[#D3003B] transition">
        <div class="text-[11px] font-medium text-slate-500">Active Requirements</div>
        <div class="mt-1.5 text-xl font-bold text-slate-900 dark:text-white tabular-nums">{{ $ov['active_requirements'] }}</div>
        <div class="text-[10px] text-rose-600 font-medium mt-0.5 tabular-nums">{{ $ov['overdue_requirements'] }} overdue</div>
    </a>
    <a href="{{ route('jobs.index') }}" class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 hover:border-[#D3003B] transition">
        <div class="text-[11px] font-medium text-slate-500">Open / Filled Vacancies</div>
        <div class="mt-1.5 text-xl font-bold text-[#1E40AF] dark:text-blue-400 tabular-nums">{{ $ov['open_vacancies'] }} <span class="text-xs font-normal text-emerald-600">/ {{ $ov['filled_vacancies'] }} filled</span></div>
    </a>
    <a href="{{ route('candidates.index') }}" class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 hover:border-[#D3003B] transition">
        <div class="text-[11px] font-medium text-slate-500">Master Candidates</div>
        <div class="mt-1.5 text-xl font-bold text-slate-900 dark:text-white tabular-nums">{{ $ov['total_candidates'] }}</div>
        <div class="text-[10px] text-emerald-600 font-medium mt-0.5 tabular-nums">+{{ $ov['new_candidates_today'] }} sourced today</div>
    </a>
    <a href="{{ route('submissions.index') }}" class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 hover:border-[#D3003B] transition">
        <div class="text-[11px] font-medium text-slate-500">Submissions / Shortlists</div>
        <div class="mt-1.5 text-xl font-bold text-slate-900 dark:text-white tabular-nums">{{ $ov['total_submissions'] }} <span class="text-xs font-normal text-emerald-600">({{ $ov['client_shortlists'] }} shortlisted)</span></div>
        <div class="text-[10px] text-amber-600 font-medium mt-0.5 tabular-nums">{{ $ov['pending_client_feedback'] }} pending feedback</div>
    </a>
    <a href="{{ route('joinings.index') }}" class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 hover:border-[#D3003B] transition">
        <div class="text-[11px] font-medium text-slate-500">Offers / Confirmed Joined</div>
        <div class="mt-1.5 text-xl font-bold text-emerald-600 tabular-nums">{{ $ov['candidates_joined'] }} <span class="text-xs font-normal text-slate-400">joined ({{ $ov['offers_accepted'] }} accepted)</span></div>
        <div class="text-[10px] text-blue-600 font-medium mt-0.5 tabular-nums">{{ $ov['upcoming_joining_dates'] }} upcoming joinings</div>
    </a>
</div>

<!-- Secondary Today Operational Pulse -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5 mb-6 text-xs">
    <a href="{{ route('candidates.index') }}" class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <span class="text-slate-500">Calls Recorded Today</span>
        <span class="text-base font-bold tabular-nums text-slate-900 dark:text-white">{{ $ov['calls_recorded_today'] }}</span>
    </a>
    <a href="{{ route('submissions.index') }}" class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <span class="text-slate-500">Profiles Submitted Today</span>
        <span class="text-base font-bold tabular-nums text-slate-900 dark:text-white">{{ $ov['profiles_submitted_today'] }}</span>
    </a>
    <a href="{{ route('interviews.index', ['date' => now()->toDateString()]) }}" class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <span class="text-slate-500">Interviews Today</span>
        <span class="text-base font-bold tabular-nums text-[#D3003B]">{{ $ov['interviews_scheduled_today'] }}</span>
    </a>
    <a href="{{ route('tasks.index') }}" class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <span class="text-slate-500">Pending Follow-ups</span>
        <span class="text-base font-bold tabular-nums text-amber-600">{{ $ov['pending_follow_ups'] }}</span>
    </a>
</div>

<!-- Recruitment Funnel & Conversion Ratios -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
    <!-- Live Lifecycle Funnel -->
    <div class="lg:col-span-7 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Recruitment Lifecycle Funnel</h2>
                <p class="text-xs text-slate-500">Click any stage bar to inspect filtered candidate applications</p>
            </div>
            <a href="{{ route('pipeline.index') }}" class="text-xs font-semibold text-[#D3003B] hover:underline">Open Visual Kanban →</a>
        </div>
        <div class="space-y-2.5 text-xs">
            @php
                $maxStageCount = max(1, collect($analytics['funnel'])->max() ?? 1);
            @endphp
            @foreach(\App\Models\CandidateApplication::MAIN_STAGES as $stageKey => $stageLabel)
                @php
                    $cnt = (int) ($analytics['funnel'][$stageKey] ?? 0);
                    $widthPct = min(100, max(4, round(($cnt / $maxStageCount) * 100)));
                @endphp
                <a href="{{ route('applications.index', ['stage' => $stageKey]) }}" class="block group">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-medium text-slate-700 dark:text-slate-300 group-hover:text-[#D3003B]">{{ $stageLabel }}</span>
                        <span class="font-bold tabular-nums text-slate-900 dark:text-white">{{ $cnt }}</span>
                    </div>
                    <div class="w-full h-2 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                        <div class="h-full bg-[#1E40AF] group-hover:bg-[#D3003B] transition-all" style="width: {{ $widthPct }}%"></div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    <!-- Conversion Intelligence & Documented Calculation Formulas -->
    <div class="lg:col-span-5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 flex flex-col justify-between">
        <div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Stage Conversion Intelligence</h2>
            <p class="text-xs text-slate-500 mb-4">Verified distinct-application ratios with explicit formulas</p>
            <div class="space-y-4 text-xs">
                @foreach([
                    ['submission_to_shortlist', 'Submission → Client Shortlist', $conv['submission_to_shortlist']],
                    ['shortlist_to_interview', 'Shortlist → Interview', $conv['shortlist_to_interview']],
                    ['interview_to_selection', 'Interview → Final Selection', $conv['interview_to_selection']],
                    ['offer_acceptance_ratio', 'Offer Release → Acceptance Ratio', $conv['offer_acceptance_ratio']],
                    ['offer_to_joining', 'Offer Accepted → Confirmed Joining', $conv['offer_to_joining']],
                ] as [$fKey, $label, $pct])
                    <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200/70 dark:border-slate-800">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $label }}</span>
                            <span class="text-sm font-bold text-[#D3003B] tabular-nums">{{ $pct }}%</span>
                        </div>
                        <div class="text-[10px] text-slate-400 mt-1 font-mono">Formula: {{ $formulas[$fKey] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<!-- Recruiter Performance & Client Hiring Tables -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <div class="lg:col-span-7 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
        <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Recruiter Productivity & Placement Leaderboard</h3>
            <a href="{{ route('management.recruiter-performance') }}" class="text-xs text-[#1E40AF] hover:underline">Full View →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                    <tr>
                        <th class="px-4 py-2.5">Recruiter</th>
                        <th class="px-4 py-2.5 text-right">Sourced</th>
                        <th class="px-4 py-2.5 text-right">Calls</th>
                        <th class="px-4 py-2.5 text-right">Submissions</th>
                        <th class="px-4 py-2.5 text-right">Active Apps</th>
                        <th class="px-4 py-2.5 text-right">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
                    @foreach($analytics['recruiter_performance'] as $rec)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-2.5 font-medium text-slate-900 dark:text-white">{{ $rec->name }}</td>
                            <td class="px-4 py-2.5 text-right">{{ $rec->sourced_candidates_count }}</td>
                            <td class="px-4 py-2.5 text-right">{{ $rec->call_logs_count }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold">{{ $rec->client_submissions_count }}</td>
                            <td class="px-4 py-2.5 text-right">{{ $rec->active_applications_count }}</td>
                            <td class="px-4 py-2.5 text-right font-bold text-emerald-600">{{ $rec->joined_applications_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="lg:col-span-5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
        <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Client Hiring Performance</h3>
            <a href="{{ route('management.client-performance') }}" class="text-xs text-[#1E40AF] hover:underline">All Clients →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                    <tr>
                        <th class="px-4 py-2.5">Client</th>
                        <th class="px-4 py-2.5 text-right">Jobs</th>
                        <th class="px-4 py-2.5 text-right">Submitted</th>
                        <th class="px-4 py-2.5 text-right">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
                    @foreach($analytics['client_performance'] as $cl)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-2.5 font-medium">
                                <a href="{{ route('clients.show', $cl) }}" class="text-slate-900 dark:text-white hover:text-[#D3003B]">{{ $cl->company_name }}</a>
                            </td>
                            <td class="px-4 py-2.5 text-right">{{ $cl->job_requirements_count }}</td>
                            <td class="px-4 py-2.5 text-right">{{ $cl->client_submissions_count }}</td>
                            <td class="px-4 py-2.5 text-right font-bold text-emerald-600">{{ $cl->joined_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
