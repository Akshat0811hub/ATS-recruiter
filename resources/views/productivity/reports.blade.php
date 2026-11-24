@extends('layouts.app')
@section('title', 'Reports & Business Intelligence')
@section('page_title', 'Comprehensive Recruitment Reports & Exports')
@section('page_subtitle', '18 operational report categories with formula-safe CSV/Excel exports and print-friendly output')

@section('content')
<div class="mb-5 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
    <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-center gap-2.5">
        <select name="report_type" onchange="this.form.submit()" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 font-semibold">
            @foreach($reportTypes as $rKey => $rLabel)
                <option value="{{ $rKey }}" {{ $reportType === $rKey ? 'selected' : '' }}>{{ $rLabel }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-3.5 py-2 rounded-lg bg-[#0B1528] text-white font-semibold">Generate Report</button>
    </form>

    <div class="flex items-center gap-2">
        <button type="button" onclick="window.print()" class="px-3.5 py-2 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold hover:bg-slate-100">
            🖨️ Print Report
        </button>
        @if(auth()->user()->hasRole(['super_admin', 'manager']))
            <a href="{{ route('reports.export', ['report_type' => $reportType]) }}" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">
                ⬇ Export Formula-Safe CSV / Excel
            </a>
        @endif
    </div>
</div>

<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
    <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ $reportTypes[$reportType] ?? 'Report' }}</h2>
        <span class="text-xs text-slate-500 tabular-nums">{{ count($rows) }} record(s)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                <tr>
                    @foreach($headers as $h)
                        <th class="px-4 py-3">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
                @forelse($rows as $row)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        @foreach($row as $cell)
                            <td class="px-4 py-2.5">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($headers) }}" class="px-4 py-8 text-center text-slate-400">No records in this report dataset.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
