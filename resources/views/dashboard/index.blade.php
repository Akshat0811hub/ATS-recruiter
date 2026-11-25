@extends('layouts.app')
@section('title', 'Overview')
@section('page_title', 'Your recruitment overview')
@section('page_subtitle', 'A clear view of your hiring progress and the work that needs your attention.')
@section('page_actions')
<div class="page-actions flex items-center gap-3"><span class="hidden text-sm text-slate-500 sm:inline">{{ now()->format('D, d M Y') }}</span><a href="{{ route('my-work') }}" class="btn-secondary"><x-icon name="briefcase" class="size-4" />My work</a></div>
@endsection
@section('content')
@php
    $ov = $analytics['overview'];
    $conv = $analytics['conversions'];
@endphp
<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([
        ['Open jobs', $ov['active_requirements'], $ov['open_vacancies'].' vacancies to fill', 'briefcase', route('jobs.index', ['active' => 1])],
        ['Candidates', $ov['total_candidates'], $ov['new_candidates_today'].' added today', 'users', route('candidates.index')],
        ['Interviews today', $ov['interviews_scheduled_today'], 'View today’s interview schedule', 'calendar', route('interviews.index', ['date' => now()->toDateString()])],
        ['Candidates joined', $ov['candidates_joined'], $ov['upcoming_joining_dates'].' upcoming joinings', 'check', route('joinings.index', ['status' => 'joined'])],
    ] as [$label, $value, $detail, $icon, $url])
    <a href="{{ $url }}" class="metric"><div class="flex items-center justify-between"><span class="text-sm font-medium text-slate-500">{{ $label }}</span><span class="rounded-lg bg-blue-50 p-2 text-blue-700"><x-icon :name="$icon" class="size-4" /></span></div><div class="metric-value">{{ number_format($value) }}</div><p class="text-sm text-slate-500">{{ $detail }}</p></a>
    @endforeach
</div>
<div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
    <section class="panel xl:col-span-2">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4"><div><h2 class="text-base font-semibold text-slate-900">Needs your attention</h2><p class="mt-1 text-sm text-slate-500">Keep the next step moving.</p></div><a href="{{ route('tasks.index') }}" class="text-sm font-medium text-blue-700 hover:underline">View tasks</a></div>
        <div class="grid gap-0 divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            @foreach([
                [$ov['pending_follow_ups'], 'Pending follow-ups', 'tasks.index', ['filter' => 'pending'], 'clock'],
                [$ov['pending_client_feedback'], 'Awaiting client feedback', 'submissions.index', ['decision' => 'pending_review'], 'send'],
                [$ov['overdue_requirements'], 'Overdue jobs', 'jobs.index', ['overdue' => 1], 'briefcase'],
            ] as [$count, $label, $target, $params, $icon])
            <a href="{{ route($target, $params) }}" class="flex items-start gap-3 p-5 hover:bg-slate-50"><x-icon :name="$icon" class="mt-1 text-slate-400" /><div><div class="text-2xl font-semibold tabular-nums {{ $count ? 'text-amber-700' : 'text-slate-900' }}">{{ $count }}</div><p class="mt-1 text-sm text-slate-600">{{ $label }}</p></div></a>
            @endforeach
        </div>
        <div class="border-t border-slate-100 px-5">
            @forelse($pendingTasks->take(4) as $task)
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-4 last:border-0"><div class="min-w-0"><a href="{{ route('tasks.index', ['filter' => 'pending']) }}" class="text-sm font-medium text-slate-900 hover:text-blue-700">{{ $task->title }}</a><p class="mt-1 text-xs text-slate-500">{{ $task->assignee?->name ?? 'Unassigned' }} · {{ $task->due_date?->format('d M') ?? 'No due date' }}</p></div><span class="shrink-0 rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600">{{ ucfirst($task->priority) }}</span></div>
            @empty
            <div class="flex items-center gap-3 py-6"><span class="rounded-full bg-emerald-50 p-2 text-emerald-700"><x-icon name="checkmark" /></span><div><p class="text-sm font-medium text-slate-800">You’re all caught up</p><p class="mt-1 text-sm text-slate-500">New follow-ups will appear here.</p></div></div>
            @endforelse
        </div>
    </section>
    <section class="panel">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4"><div><h2 class="text-base font-semibold text-slate-900">Today’s schedule</h2><p class="mt-1 text-sm text-slate-500">Interviews · India Standard Time</p></div><a href="{{ route('calendar') }}" class="icon-button" aria-label="Open calendar"><x-icon name="calendar" /></a></div>
        <div class="px-5">
            @forelse($todaysInterviews->take(4) as $interview)
            <a href="{{ route('interviews.index', ['date' => now()->toDateString()]) }}" class="flex items-start gap-3 border-b border-slate-100 py-4 last:border-0"><span class="rounded-lg bg-blue-50 px-2 py-2 text-xs font-semibold text-blue-800">{{ substr($interview->interview_time ?? '', 0, 5) }}</span><div class="min-w-0"><p class="text-sm font-semibold text-slate-900">{{ $interview->candidate?->full_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $interview->round_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $interview->jobRequirement?->title }}</p></div></a>
            @empty
            <div class="empty-state"><x-icon name="calendar" class="mx-auto mb-3 size-7 text-slate-300" /><p>No interviews today.</p><a href="{{ route('interviews.index') }}" class="mt-3 inline-block font-medium text-blue-700">View upcoming interviews</a></div>
            @endforelse
        </div>
    </section>
</div>
<section class="panel mb-6 p-5 sm:p-6">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-4"><div><h2 class="text-base font-semibold text-slate-900">Hiring pipeline</h2><p class="mt-1 text-sm text-slate-500">Applications at each stage. Select a stage to view them.</p></div><a href="{{ route('pipeline.index') }}" class="btn-secondary"><x-icon name="pipeline" class="size-4" />Open pipeline</a></div>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
        @foreach([
            ['sourced', 'Sourced'], ['screened', 'Screened'], ['submitted', 'Submitted'], ['client_shortlisted', 'Shortlisted'], ['interview_scheduled', 'Interview scheduled'], ['offer_accepted', 'Offer accepted'], ['joined', 'Joined'],
        ] as [$stage, $label])
        <a href="{{ route('applications.index', ['stage' => $stage]) }}" class="rounded-lg border border-slate-200 p-4 hover:border-blue-300 hover:bg-blue-50/50"><div class="mb-3 h-1 w-7 rounded-full {{ $stage === 'joined' ? 'bg-emerald-500' : 'bg-blue-400' }}"></div><p class="text-2xl font-semibold tabular-nums">{{ $analytics['funnel'][$stage] ?? 0 }}</p><p class="mt-2 text-sm text-slate-500">{{ $label }}</p></a>
        @endforeach
    </div>
    <p class="mt-4 text-xs text-slate-500">Other stages and paused applications are available in the full pipeline.</p>
</section>
<details class="panel" {{ count(array_filter($filters)) ? 'open' : '' }}>
    <summary class="flex items-center justify-between gap-3 p-5"><span><span class="block text-base font-semibold">Performance & detailed reports</span><span class="mt-1 block text-sm text-slate-500">Filter by period, client, team or recruiter.</span></span><x-icon name="chevron" class="text-slate-400" /></summary>
    <div class="border-t border-slate-200 p-5">
        <form method="GET" action="{{ route('dashboard') }}" class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" x-data="{ period: @js($filters['period'] ?? 'all') }">
            <label class="text-sm font-medium text-slate-600">Period<select name="period" x-model="period" class="field mt-1">@foreach(['all' => 'All time', 'today' => 'Today', 'yesterday' => 'Yesterday', 'this_week' => 'This week', 'this_month' => 'This month', 'custom' => 'Custom dates'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
            <label x-show="period === 'custom'" x-cloak class="text-sm font-medium text-slate-600">From date<input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" :disabled="period !== 'custom'" :required="period === 'custom'" class="field mt-1"></label>
            <label x-show="period === 'custom'" x-cloak class="text-sm font-medium text-slate-600">To date<input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" :disabled="period !== 'custom'" :required="period === 'custom'" class="field mt-1"></label>
            @foreach([['client_id', 'Client', $clients, 'company_name'], ['team_id', 'Team', $teams, 'name'], ['recruiter_id', 'Recruiter', $recruiters, 'name'], ['job_id', 'Job', $jobs, 'title']] as [$name, $label, $options, $field])
            <label class="text-sm font-medium text-slate-600">{{ $label }}<select name="{{ $name }}" class="field mt-1"><option value="">All {{ strtolower($label) }}s</option>@foreach($options as $option)<option value="{{ $option->id }}" @selected(($filters[$name] ?? '') == $option->id)>{{ $option->$field }}</option>@endforeach</select></label>
            @endforeach
            <div class="flex items-end gap-2"><button type="submit" class="btn-primary">Apply filters</button><a href="{{ route('dashboard') }}" class="btn-secondary">Reset</a></div>
        </form>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach(['submission_to_shortlist' => 'Submission to shortlist', 'shortlist_to_interview' => 'Shortlist to interview', 'interview_to_selection' => 'Interview to selection', 'offer_acceptance_ratio' => 'Offer acceptance', 'offer_to_joining' => 'Offer to joining'] as $key => $label)
            <div class="rounded-lg border border-slate-200 p-4"><div class="flex items-center justify-between gap-2"><span class="text-sm text-slate-600">{{ $label }}</span><strong class="text-lg font-semibold text-blue-800">{{ $conv[$key] }}%</strong></div><details class="mt-2 text-xs text-slate-500"><summary>How this is calculated</summary><p class="mt-2">{{ $analytics['formulas'][$key] }}</p></details></div>
            @endforeach
        </div>
        <div class="mt-5 flex flex-wrap gap-3"><a href="{{ route('management.recruiter-performance') }}" class="btn-secondary">Recruiter performance</a><a href="{{ route('management.client-performance') }}" class="btn-secondary">Client performance</a><a href="{{ route('reports.index') }}" class="btn-primary">View reports</a></div>
    </div>
</details>
@endsection
