@extends('layouts.app')
@section('title', 'Audit log')
@section('page_title', 'Audit log')
@section('page_subtitle', 'Review who changed records and when.')

@section('content')
<form method="GET" action="{{ route('admin.audit-logs') }}" class="mb-5 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 flex flex-wrap items-center gap-2.5 text-xs">
    <input type="text" name="action" value="{{ request('action') }}" placeholder="Filter by action (e.g. candidate, interview, backup)..." class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent w-72">
    <select name="user_id" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
        <option value="">All Users</option>
        @foreach($users as $u)
            <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
        @endforeach
    </select>
    <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-[#0B1528] text-white font-semibold">Search Audit Trail</button>
</form>

<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                <tr>
                    <th class="px-4 py-3">Timestamp (IST)</th>
                    <th class="px-4 py-3">User & Role</th>
                    <th class="px-4 py-3">Action</th>
                    <th class="px-4 py-3">Record</th>
                    <th class="px-4 py-3">Changes / Reason</th>
                    <th class="px-4 py-3">IP Address</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
                @forelse($logs as $log)
                    <tr>
                        <td class="px-4 py-3 text-slate-500">{{ $log->created_at?->format('d M Y, H:i:s') }}</td>
                        <td class="px-4 py-3">
                            <div class="font-bold text-slate-900 dark:text-white">{{ $log->user_name }}</div>
                            <div class="text-[10px] uppercase text-slate-400">{{ $log->user_role }}</div>
                        </td>
                        <td class="px-4 py-3 font-mono font-bold text-[#D3003B]">{{ $log->action }}</td>
                        <td class="px-4 py-3">{{ $log->auditable_type }} #{{ $log->auditable_id }}</td>
                        <td class="px-4 py-3 max-w-md truncate font-mono text-[11px] text-slate-600 dark:text-slate-300">
                            @if($log->reason)<div class="font-sans font-semibold text-amber-700">Reason: {{ $log->reason }}</div>@endif
                            {{ json_encode($log->new_values) }}
                        </td>
                        <td class="px-4 py-3 text-slate-400">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No audit entries found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-slate-100 dark:border-slate-800">{{ $logs->links() }}</div>
</div>
@endsection
