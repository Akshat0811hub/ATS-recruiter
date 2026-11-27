@extends('layouts.app')
@section('title', 'Users')
@section('page_title', 'User Accounts & Access')
@section('page_subtitle', 'Create employee or Client HR accounts, reset passwords, and manage active status.')

@section('content')
<div x-data="{
    showCreateUser: {{ request()->boolean('create') || ($errors->any() && old('_form') === 'create_user') ? 'true' : 'false' }},
    editUser: null
}">
    @if(session('invitation_link_once'))
        <div class="mb-5 p-4 rounded-xl bg-amber-50 border border-amber-200 text-xs space-y-1.5">
            <div class="font-bold text-amber-900">One-Time Account Invitation Link Generated</div>
            <p class="text-amber-800">Share this secure activation link with the user so they can set their own password:</p>
            <div class="p-2 rounded-lg bg-white border border-amber-200 font-mono text-[11px] text-slate-900 break-all select-all">{{ session('invitation_link_once') }}</div>
        </div>
    @endif

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 bg-white p-4 rounded-xl border border-slate-200 text-xs">
        <form method="GET" action="{{ route('admin.users') }}" class="flex flex-wrap items-center gap-2.5">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, email, employee code..." class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white w-64">
            <select name="role_id" class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white">
                <option value="">All Roles</option>
                @foreach($roles as $r)
                    <option value="{{ $r->id }}" {{ request('role_id') == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-[#0B1528] text-white font-semibold">Search</button>
            @if(request()->anyFilled(['search', 'role_id']))
                <a href="{{ route('admin.users') }}" class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-slate-600 font-semibold hover:bg-slate-50">Reset</a>
            @endif
        </form>
        <button type="button" @click="showCreateUser = true" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">+ Create / Invite User Account</button>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase text-[10px]">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Name & Email</th>
                        <th class="px-4 py-3">Role & Team / Client</th>
                        <th class="px-4 py-3">Daily Targets (Src/Call/Sub)</th>
                        <th class="px-4 py-3">2FA & Last Login</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 tabular-nums">
                    @foreach($users as $u)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono font-bold text-[#D3003B] whitespace-nowrap">{{ $u->employee_code }}</td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-900">{{ $u->name }}</div>
                                <div class="text-[11px] text-slate-500">{{ $u->email }} @if($u->designation) • {{ $u->designation }} @endif</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800 text-[10px] font-bold uppercase">{{ $u->role?->name }}</span>
                                <div class="text-[11px] text-slate-500 mt-1">{{ $u->team?->name ?? $u->client?->company_name ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $u->daily_sourcing_target }} / {{ $u->daily_call_target }} / {{ $u->daily_submission_target }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div>2FA: <strong>{{ $u->two_factor_enabled ? 'Enabled' : 'Off' }}</strong></div>
                                <div class="text-[10px] text-slate-400">{{ $u->last_login_at?->format('d M Y H:i') ?? 'Never' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $u->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $u->is_active ? 'Active' : 'Deactivated' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-2">
                                    <button type="button"
                                            @click="editUser = {{ Js::from([
                                                'id' => $u->id,
                                                'name' => $u->name,
                                                'phone' => $u->phone ?? '',
                                                'designation' => $u->designation ?? '',
                                                'role_id' => $u->role_id,
                                                'team_id' => $u->team_id ?? '',
                                                'client_id' => $u->client_id ?? '',
                                                'daily_sourcing_target' => $u->daily_sourcing_target,
                                                'daily_call_target' => $u->daily_call_target,
                                                'daily_submission_target' => $u->daily_submission_target,
                                            ]) }}"
                                            class="px-2.5 py-1 rounded border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-[11px] font-semibold">
                                        Edit
                                    </button>
                                    @if($u->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.users.toggle-status', $u) }}">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 rounded text-[11px] font-semibold {{ $u->is_active ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                                                {{ $u->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">{{ $users->links() }}</div>
    </div>

    <!-- Create User Modal -->
    <div x-show="showCreateUser" x-cloak role="dialog" aria-modal="true" aria-label="Create user account" @keydown.escape.window="showCreateUser = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 overflow-y-auto">
        <div @click.away="showCreateUser = false" class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-slate-200 p-6 text-xs">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                <h3 class="text-sm font-bold text-slate-900">Create / Invite Employee or Client HR Account</h3>
                <button type="button" @click="showCreateUser = false" class="text-slate-400">✕</button>
            </div>
            <form method="POST" action="{{ route('admin.users.store') }}" class="mt-4 space-y-3.5">
                @csrf
                <input type="hidden" name="_form" value="create_user">
                @if($errors->any() && old('_form') === 'create_user')
                    <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-rose-800">
                        <ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold mb-1">Full Name *</label>
                        <input type="text" name="name" value="{{ old('name') }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Email Address *</label>
                        <input type="email" name="email" value="{{ old('email') }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Designation</label>
                        <input type="text" name="designation" value="{{ old('designation', 'Talent Acquisition Specialist') }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">System Role *</label>
                        <select name="role_id" required class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                            @foreach($roles as $r)
                                <option value="{{ $r->id }}" @selected(old('role_id') == $r->id)>{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Assign Team</label>
                        <select name="team_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                            <option value="">-- Optional Team --</option>
                            @foreach($teams as $tm)
                                <option value="{{ $tm->id }}" @selected(old('team_id') == $tm->id)>{{ $tm->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Client Organisation (Required for Client HR)</label>
                        <select name="client_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                            <option value="">-- Internal Staff --</option>
                            @foreach($clients as $cl)
                                <option value="{{ $cl->id }}" @selected(old('client_id') == $cl->id)>{{ $cl->company_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Initial Password (Optional if Inviting)</label>
                        <input type="password" name="password" autocomplete="new-password" placeholder="Min 8 chars, or leave blank to auto-generate" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-200">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="send_invitation" value="1" @checked(old('send_invitation', true))>
                        <span>Generate secure invitation link so user sets their own password</span>
                    </label>
                    <div class="flex gap-2">
                        <button type="button" @click="showCreateUser = false" class="px-3.5 py-2 rounded-lg border border-slate-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">Create Account</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User / Reset Password Modal -->
    <div x-show="editUser !== null" x-cloak role="dialog" aria-modal="true" aria-label="Edit user account" @keydown.escape.window="editUser = null" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 overflow-y-auto">
        <template x-if="editUser">
            <div @click.away="editUser = null" class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-slate-200 p-6 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <h3 class="text-sm font-bold text-slate-900">Edit User Account, Targets & Password</h3>
                    <button type="button" @click="editUser = null" class="text-slate-400">✕</button>
                </div>
                <form method="POST" :action="'{{ url('/admin/users') }}/' + editUser.id" class="mt-4 space-y-3.5">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold mb-1">Full Name *</label>
                            <input type="text" name="name" x-model="editUser.name" required class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Phone</label>
                            <input type="text" name="phone" x-model="editUser.phone" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Designation</label>
                            <input type="text" name="designation" x-model="editUser.designation" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">System Role *</label>
                            <select name="role_id" x-model="editUser.role_id" required class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                                @foreach($roles as $r)
                                    <option value="{{ $r->id }}">{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Team</label>
                            <select name="team_id" x-model="editUser.team_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                                <option value="">-- Optional Team --</option>
                                @foreach($teams as $tm)
                                    <option value="{{ $tm->id }}">{{ $tm->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Client Organisation (if Client HR)</label>
                            <select name="client_id" x-model="editUser.client_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                                <option value="">-- Internal Staff --</option>
                                @foreach($clients as $cl)
                                    <option value="{{ $cl->id }}">{{ $cl->company_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Daily Sourcing Target</label>
                            <input type="number" min="0" name="daily_sourcing_target" x-model="editUser.daily_sourcing_target" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Daily Call Target</label>
                            <input type="number" min="0" name="daily_call_target" x-model="editUser.daily_call_target" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Daily Submission Target</label>
                            <input type="number" min="0" name="daily_submission_target" x-model="editUser.daily_submission_target" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Reset Password (Optional)</label>
                            <input type="password" name="password" autocomplete="new-password" placeholder="Leave blank to keep existing password" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-200">
                        <button type="button" @click="editUser = null" class="px-3.5 py-2 rounded-lg border border-slate-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">Save Changes</button>
                    </div>
                </form>
            </div>
        </template>
    </div>
</div>
@endsection
