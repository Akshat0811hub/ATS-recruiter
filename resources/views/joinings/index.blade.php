@extends('layouts.app')
@section('title', 'Joining & Placement Management')
@section('page_title', 'Joining, Placements & Replacement Warranty Management')
@section('page_subtitle', 'Confirm joinings, automatically update remaining vacancy counts, log post-joining check-ins, and manage replacements')

@section('content')
<!-- Filters -->
<form method="GET" action="{{ route('joinings.index') }}" class="mb-5 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 flex flex-wrap items-center gap-2.5 text-xs">
    <select name="status" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
        <option value="">All Joining Statuses</option>
        @foreach(\App\Models\JoiningRecord::STATUSES as $sk => $sl)
            <option value="{{ $sk }}" {{ request('status') === $sk ? 'selected' : '' }}>{{ $sl }}</option>
        @endforeach
    </select>
    <select name="client_id" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
        <option value="">All Clients</option>
        @foreach($clients as $cl)
            <option value="{{ $cl->id }}" {{ request('client_id') == $cl->id ? 'selected' : '' }}>{{ $cl->company_name }}</option>
        @endforeach
    </select>
    <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-[#0B1528] text-white font-semibold">Filter</button>
</form>

<!-- Joinings & Placements Table -->
<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden mb-6">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                <tr>
                    <th class="px-4 py-3">Joining / Placement ID</th>
                    <th class="px-4 py-3">Candidate & Job</th>
                    <th class="px-4 py-3">Expected / Actual Date</th>
                    <th class="px-4 py-3 text-right">Final Salary & Fee</th>
                    <th class="px-4 py-3">Warranty Expiry</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Lifecycle Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
                @forelse($joinings as $jon)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono font-bold text-[#D3003B]">
                            <div>{{ $jon->joining_code }}</div>
                            @if($jon->placement)
                                <div class="text-[10px] text-emerald-600">{{ $jon->placement->placement_code }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($jon->candidate)
                                <a href="{{ route('candidates.show', $jon->candidate) }}" class="font-bold text-slate-900 dark:text-white hover:text-[#D3003B]">{{ $jon->candidate->full_name }}</a>
                            @endif
                            <div class="text-[11px] text-slate-500">{{ $jon->jobRequirement?->title }} ({{ $jon->client?->company_name }})</div>
                            <div class="text-[10px] text-slate-400">Job Remaining Vacancies: <strong>{{ $jon->jobRequirement?->remaining_count }}</strong> / {{ $jon->jobRequirement?->openings_count }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div>Exp: {{ $jon->expected_joining_date?->format('d M Y') }}</div>
                            @if($jon->actual_joining_date)
                                <div class="font-bold text-emerald-600">Joined: {{ $jon->actual_joining_date->format('d M Y') }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="font-semibold">{{ number_format((float)$jon->final_salary) }} {{ $jon->currency }}</div>
                            @if($jon->placement_value)
                                <div class="text-[11px] text-emerald-700 font-bold">Fee: {{ number_format((float)$jon->placement_value) }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($jon->replacement_expiry_date)
                                <div class="font-medium">{{ $jon->replacement_expiry_date->format('d M Y') }}</div>
                                <div class="text-[10px] text-slate-400">{{ $jon->replacement_period_days }}d window</div>
                            @else
                                <span class="text-slate-400">Pending Joining</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ in_array($jon->status, ['joined', 'placement_completed']) ? 'bg-emerald-100 text-emerald-800' : (in_array($jon->status, ['did_not_join', 'replacement_required']) ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                                {{ \App\Models\JoiningRecord::STATUSES[$jon->status] ?? $jon->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 space-y-2">
                            @if($jon->status === 'joining_pending')
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <form method="POST" action="{{ route('joinings.confirm', $jon) }}" class="inline-flex items-center gap-1">
                                        @csrf
                                        <input type="date" name="actual_joining_date" value="{{ now()->toDateString() }}" required class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-transparent text-[11px]">
                                        <button type="submit" class="px-2.5 py-1 rounded bg-emerald-600 text-white font-semibold text-[11px]">Confirm Joined</button>
                                    </form>
                                    <form method="POST" action="{{ route('joinings.did-not-join', $jon) }}" class="inline-flex items-center gap-1">
                                        @csrf
                                        <input type="text" name="non_joining_reason" required placeholder="Reason..." class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-transparent text-[11px] w-28">
                                        <button type="submit" class="px-2 py-1 rounded bg-rose-600 text-white font-semibold text-[11px]">Did Not Join</button>
                                    </form>
                                </div>
                            @endif

                            @if($jon->status === 'joined')
                                <form method="POST" action="{{ route('joinings.checkin', $jon) }}" class="flex flex-wrap items-center gap-1">
                                    @csrf
                                    <select name="checkin_interval" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-[10px]">
                                        <option value="30-Day">30-Day Check-in</option>
                                        <option value="60-Day">60-Day Check-in</option>
                                        <option value="90-Day">90-Day Closure</option>
                                    </select>
                                    <input type="text" name="checkin_notes" required placeholder="Retention check-in notes..." class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-transparent text-[10px] w-32">
                                    <label class="inline-flex items-center gap-1 text-[10px]">
                                        <input type="checkbox" name="mark_placement_completed" value="1">
                                        <span>Close</span>
                                    </label>
                                    <button type="submit" class="px-2 py-1 rounded bg-[#0B1528] text-white font-semibold text-[10px]">Log</button>
                                </form>

                                @if(auth()->user()->hasRole(['super_admin', 'manager']))
                                    <form method="POST" action="{{ route('joinings.replacement', $jon) }}" class="flex flex-wrap items-center gap-1 pt-1 border-t border-slate-100 dark:border-slate-800">
                                        @csrf
                                        <input type="date" name="exit_date" value="{{ now()->toDateString() }}" required class="px-1.5 py-0.5 rounded border border-rose-300 text-[10px]">
                                        <input type="text" name="exit_reason" required placeholder="Attrition / Exit reason..." class="px-1.5 py-0.5 rounded border border-rose-300 text-[10px] w-32">
                                        <input type="hidden" name="reopen_vacancy" value="1">
                                        <button type="submit" class="px-2 py-0.5 rounded bg-rose-600 text-white font-semibold text-[10px]">Open Replacement Vacancy</button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No joining records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-slate-100 dark:border-slate-800">
        {{ $joinings->links() }}
    </div>
</div>
@endsection
