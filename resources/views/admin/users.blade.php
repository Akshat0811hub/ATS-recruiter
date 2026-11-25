@extends('layouts.app')
@section('title', 'Users')
@section('page_title', 'Users')
@section('page_subtitle', 'Invite colleagues and clients, and manage account access.')

@section('content')
<div x-data="{ showCreateUser: {{ request()->boolean('create') || $errors->any() ? 'true' : 'false' }} }">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 text-xs">
        <form method="GET" action="{{ route('admin.users') }}" class="flex flex-wrap items-center gap-2.5">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, email, employee code..." class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent w-64">
            <select name="role_id" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                <option value="">All Roles</option>
                @foreach($roles as $r)
                    <option value="{{ $r->id }}" {{ request('role_id') == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-[#0B1528] text-white font-semibold">Search</button>
        </form>
        <button type="button" @click="showCreateUser = true" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">+ Create / Invite User Account</button>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                <tr>
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3">Name & Email</th>
                    <th class="px-4 py-3">Role & Team / Client</th>
                    <th class="px-4 py-3">Daily Targets (Src/Call/Sub)</th>
                    <th class="px-4 py-3">2FA & Last Login</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
                @foreach($users as $u)
                    <tr>
                        <td class="px-4 py-3 font-mono font-bold text-[#D3003B]">{{ $u->employee_code }}</td>
                        <td class="px-4 py-3">
                            <div class="font-bold text-slate-900 dark:text-white">{{ $u->name }}</div>
                            <div class="text-[11px] text-slate-500">{{ $u->email }} • {{ $u->designation }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800 text-[10px] font-bold uppercase">{{ $u->role?->name }}</span>
                            <div class="text-[11px] text-slate-500 mt-1">{{ $u->team?->name ?? $u->client?->company_name ?? '-' }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $u->daily_sourcing_target }} / {{ $u->daily_call_target }} / {{ $u->daily_submission_target }}</td>
                        <td class="px-4 py-3">
                            <div>2FA: <strong>{{ $u->two_factor_enabled ? 'Enabled' : 'Off' }}</strong></div>
                            <div class="text-[10px] text-slate-400">{{ $u->last_login_at?->format('d M Y H:i') ?? 'Never' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $u->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                {{ $u->is_active ? 'Active' : 'Deactivated' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if($u->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.toggle-status', $u) }}">
                                    @csrf
                                    <button type="submit" class="px-2.5 py-1 rounded text-[11px] font-semibold {{ $u->is_active ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                                        {{ $u->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-4 border-t border-slate-100 dark:border-slate-800">{{ $users->links() }}</div>
    </div>

    <!-- Create User Modal -->
    <div x-show="showCreateUser" x-cloak role="dialog" aria-modal="true" aria-label="admin form" @keydown.escape.window="showCreateUser = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4">
        <div @click.away="showCreateUser = false" class="w-full max-w-2xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 text-xs">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white pb-3 border-b border-slate-200">Create / Invite Employee or Client HR Account</h3>
            <form method="POST" action="{{ route('admin.users.store') }}" class="mt-4 space-y-3.5">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold mb-1">Full Name *</label>
                        <input type="text" name="name" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Email Address *</label>
                        <input type="email" name="email" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Phone</label>
                        <input type="text" name="phone" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Designation</label>
                        <input type="text" name="designation" value="Talent Acquisition Specialist" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">System Role *</label>
                        <select name="role_id" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            @foreach($roles as $r)
                                <option value="{{ $r->id }}">{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Assign Team</label>
                        <select name="team_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            <option value="">-- Optional Team --</option>
                            @foreach($teams as $tm)
                                <option value="{{ $tm->id }}">{{ $tm->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Client Organisation (if Client Role)</label>
                        <select name="client_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            <option value="">-- Internal Staff --</option>
                            @foreach($clients as $cl)
                                <option value="{{ $cl->id }}">{{ $cl->company_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Initial Password</label>
                        <input type="password" name="password" value="OrbiTouch@2026!" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                </div>
                <div class="flex items-center justify-between pt-3 border-t border-slate-200">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="send_invitation" value="1">
                        <span>Generate secure invitation link token</span>
                    </label>
                    <div class="flex gap-2">
                        <button type="button" @click="showCreateUser = false" class="px-3.5 py-2 rounded-lg border border-slate-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">Create Account</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
