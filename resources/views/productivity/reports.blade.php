@extends('layouts.app')
@section('title', 'Reports')
@section('page_title', 'Reports & Analytics Exports')
@section('page_subtitle', 'Filter recruitment datasets by date range, recruiter, client, job requirement, and status.')

@section('content')
<div class="mb-5 bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-xs space-y-4 text-xs">
    <form method="GET" action="{{ route('reports.index') }}" class="space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3">
            <div class="sm:col-span-2">
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Report Dataset</label>
                <select name="report_type" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white font-semibold text-slate-900">
                    @foreach($reportTypes as $rKey => $rLabel)
                        <option value="{{ $rKey }}" {{ $reportType === $rKey ? 'selected' : '' }}>{{ $rLabel }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">From Date</label>
                <input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-900">
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">To Date</label>
                <input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-900">
            </div>

            @if(!auth()->user()->isRecruiter())
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Recruiter</label>
                    <select name="recruiter_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-900">
                        <option value="">All Recruiters</option>
                        @foreach($recruiters as $rec)
                            <option value="{{ $rec->id }}" {{ (string)($filters['recruiter_id'] ?? '') === (string)$rec->id ? 'selected' : '' }}>{{ $rec->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Client</label>
                <select name="client_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-900">
                    <option value="">All Clients</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}" {{ (string)($filters['client_id'] ?? '') === (string)$client->id ? 'selected' : '' }}>{{ $client->company_name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Job Requirement</label>
                <select name="job_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-900">
                    <option value="">All Job Requirements</option>
                    @foreach($jobs as $job)
                        <option value="{{ $job->id }}" {{ (string)($filters['job_id'] ?? '') === (string)$job->id ? 'selected' : '' }}>[{{ $job->job_code }}] {{ $job->title }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Status / Stage</label>
                <input type="text" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="e.g. shortlisted, joined" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-900">
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100">
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="px-4 py-2 rounded-lg bg-[#0B1528] hover:bg-slate-800 text-white font-semibold transition">
                    Apply Filters
                </button>
                <a href="{{ route('reports.index', ['report_type' => $reportType]) }}" class="px-3.5 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-semibold transition">
                    Reset Filters
                </a>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="window.print()" class="px-3.5 py-2 rounded-lg border border-slate-300 bg-white font-semibold text-slate-700 hover:bg-slate-50 transition">
                    Print Report
                </button>
                @if(auth()->user()->hasPermission('reports.export') || auth()->user()->hasRole(['super_admin', 'manager']))
                    <a href="{{ route('reports.export', array_merge(request()->query(), ['report_type' => $reportType])) }}" class="px-4 py-2 rounded-lg bg-[#D3003B] hover:bg-[#b50032] text-white font-semibold transition">
                        Export Filtered CSV
                    </a>
                @endif
            </div>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-xs">
    <div class="p-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-sm font-bold text-slate-900">{{ $reportTypes[$reportType] ?? 'Report' }}</h2>
            @if(!empty($filters['from_date']) || !empty($filters['to_date']))
                <p class="text-[11px] text-slate-500 mt-0.5">
                    Date window: {{ $filters['from_date'] ?? 'Start' }} to {{ $filters['to_date'] ?? 'Present' }}
                </p>
            @endif
        </div>
        <span class="text-xs font-semibold text-slate-600 tabular-nums">{{ count($rows) }} record(s)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-600 uppercase text-[10px] tracking-wider border-b border-slate-200">
                <tr>
                    @foreach($headers as $h)
                        <th class="px-4 py-3 font-semibold whitespace-nowrap">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 tabular-nums text-slate-800">
                @forelse($rows as $row)
                    <tr class="hover:bg-slate-50">
                        @foreach($row as $cell)
                            <td class="px-4 py-2.5 align-top">{{ $cell !== null && $cell !== '' ? $cell : '—' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($headers) }}" class="px-4 py-10 text-center text-slate-400">No records match the selected filters in this report dataset.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
