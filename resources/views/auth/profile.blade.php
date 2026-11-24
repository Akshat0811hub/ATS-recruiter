@extends('layouts.app')
@section('title', 'My Profile & Security')
@section('page_title', 'My Profile & Account Security')
@section('page_subtitle', 'Manage personal information, Two-Factor Authentication (2FA), active sessions, and login history')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <div class="lg:col-span-7 space-y-6">
        <!-- Profile Details Form -->
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-4">Account Profile & Password</h2>
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4 text-xs">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Full Name</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Phone Number</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Designation</label>
                        <input type="text" name="designation" value="{{ old('designation', $user->designation) }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Timezone</label>
                        <input type="text" name="timezone" value="{{ old('timezone', $user->timezone ?? 'Asia/Kolkata') }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Theme Preference</label>
                        <select name="theme_preference" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            <option value="light" {{ $user->theme_preference === 'light' ? 'selected' : '' }}>Light Theme</option>
                            <option value="dark" {{ $user->theme_preference === 'dark' ? 'selected' : '' }}>Dark Theme</option>
                        </select>
                    </div>
                </div>
                <div class="pt-4 border-t border-slate-200 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Current Password</label>
                        <input type="password" name="current_password" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">New Password</label>
                        <input type="password" name="new_password" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Confirm Password</label>
                        <input type="password" name="new_password_confirmation" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">Save Profile Changes</button>
                </div>
            </form>
        </div>

        <!-- Two-Factor Authentication & Session Revocation -->
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 text-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Two-Factor Authentication (TOTP)</h2>
                    <p class="text-slate-500 mt-0.5">Mandatory for Super Admin accounts in production; recommended for all employees.</p>
                </div>
                <form method="POST" action="{{ route('profile.two-factor') }}">
                    @csrf
                    <button type="submit" class="px-3.5 py-2 rounded-lg font-semibold {{ $user->two_factor_enabled ? 'bg-rose-600 text-white' : 'bg-[#0B1528] text-white' }}">
                        {{ $user->two_factor_enabled ? 'Disable 2FA' : 'Enable 2FA' }}
                    </button>
                </form>
            </div>

            @if($user->two_factor_enabled && $user->two_factor_secret)
                <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-2">
                    <div><span class="font-semibold">Authenticator Secret Key:</span> <code class="font-mono text-[#D3003B]">{{ $user->two_factor_secret }}</code></div>
                    @if($currentTotpPreview)
                        <div><span class="font-semibold">Current Active TOTP Code:</span> <code class="font-mono font-bold text-emerald-600">{{ $currentTotpPreview }}</code></div>
                    @endif
                    <div>
                        <span class="font-semibold">Recovery Codes:</span>
                        <div class="mt-1 grid grid-cols-2 sm:grid-cols-4 gap-2 font-mono text-[11px]">
                            @foreach(json_decode((string)$user->two_factor_recovery_codes, true) ?: [] as $rc)
                                <span class="px-2 py-1 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">{{ $rc }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <div class="font-semibold text-slate-900 dark:text-white">Browser Session Security</div>
                    <div class="text-slate-500">Immediately revoke all other active browser sessions across devices.</div>
                </div>
                <form method="POST" action="{{ route('profile.revoke-sessions') }}">
                    @csrf
                    <button type="submit" class="px-3.5 py-2 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold hover:bg-slate-100">Revoke Other Sessions</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Login Activity History -->
    <div class="lg:col-span-5">
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-4">Recent Login & Session Activity</h2>
            <div class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                @forelse($user->loginActivities as $act)
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <div class="font-semibold text-slate-800 dark:text-slate-200">{{ strtoupper(str_replace('_', ' ', $act->event_type)) }}</div>
                            <div class="text-[11px] text-slate-400">IP: {{ $act->ip_address }}</div>
                        </div>
                        <div class="text-[11px] text-slate-500 tabular-nums">{{ $act->occurred_at?->format('d M Y, H:i') }}</div>
                    </div>
                @empty
                    <div class="py-4 text-slate-400">No login activity recorded yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
