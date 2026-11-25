@extends('layouts.app')
@section('title', 'My Profile')
@section('page_title', 'My Profile')
@section('page_subtitle', 'Update your personal details, password, and account security controls.')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <div class="lg:col-span-7 space-y-6">
        <!-- Profile Details Form -->
        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-xs">
            <h2 class="text-sm font-bold text-slate-900 mb-4">Account Profile & Password</h2>
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4 text-xs">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Full Name <span class="text-rose-600">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-900">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Phone Number</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-900">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Designation</label>
                        <input type="text" name="designation" value="{{ old('designation', $user->designation) }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-900">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Timezone <span class="text-rose-600">*</span></label>
                        <input type="text" name="timezone" value="{{ old('timezone', $user->timezone ?? 'Asia/Kolkata') }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-900">
                    </div>
                    <input type="hidden" name="theme_preference" value="light">
                </div>
                <div class="pt-4 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Current Password</label>
                        <input type="password" name="current_password" autocomplete="current-password" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-900">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">New Password</label>
                        <input type="password" name="new_password" autocomplete="new-password" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-900">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Confirm Password</label>
                        <input type="password" name="new_password_confirmation" autocomplete="new-password" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-900">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-[#D3003B] hover:bg-[#b50032] text-white font-semibold transition">Save Profile Changes</button>
                </div>
            </form>
        </div>

        <!-- Two-Factor Authentication & Session Revocation -->
        <div class="bg-white rounded-xl border border-slate-200 p-6 text-xs space-y-5 shadow-xs">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-bold text-slate-900">Two-Factor Authentication (TOTP)</h2>
                        @if($user->two_factor_enabled)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>
                        @elseif(!empty($pendingTwoFactorSecret))
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">Pending Verification</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">Not Enabled</span>
                        @endif
                    </div>
                    <p class="text-slate-500 mt-1">Mandatory for Super Admin accounts in production; recommended for all OrbiTouch team members.</p>
                    @if($user->two_factor_enabled && $user->two_factor_confirmed_at)
                        <p class="text-[11px] text-slate-500 mt-0.5">Verified on {{ $user->two_factor_confirmed_at->format('d M Y, H:i') }}</p>
                    @endif
                </div>

                @if(!$user->two_factor_enabled && empty($pendingTwoFactorSecret))
                    <form method="POST" action="{{ route('profile.two-factor') }}">
                        @csrf
                        <button type="submit" class="px-3.5 py-2 rounded-lg bg-[#0B1528] hover:bg-slate-800 text-white font-semibold transition">
                            Set Up 2FA
                        </button>
                    </form>
                @endif
            </div>

            @if(!empty($oneTimeRecoveryCodes) && is_array($oneTimeRecoveryCodes))
                <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 space-y-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-bold text-amber-900">Save Your One-Time Recovery Codes</div>
                            <p class="text-[11px] text-amber-800 mt-0.5">Store these recovery codes in a secure password manager now. For your security, they will not be displayed again after you leave this page.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 font-mono text-[11px]">
                        @foreach($oneTimeRecoveryCodes as $rc)
                            <span class="px-2.5 py-1.5 rounded-lg bg-white border border-amber-200 text-slate-900 font-semibold text-center select-all">{{ $rc }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(!$user->two_factor_enabled && !empty($pendingTwoFactorSecret))
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-4">
                    <div class="space-y-1.5">
                        <div class="font-bold text-slate-900">Step 1: Add Setup Key to Your Authenticator App</div>
                        <p class="text-slate-600">Open Google Authenticator, Microsoft Authenticator, or Authy, choose <strong>Enter a setup key</strong>, and enter the Base32 key below (Time-based / 30s):</p>
                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <code class="px-3 py-1.5 rounded-lg bg-white border border-slate-300 font-mono text-sm font-bold tracking-wider text-slate-900 select-all">{{ $pendingTwoFactorSecret }}</code>
                        </div>
                        @if(!empty($pendingOtpAuthUri))
                            <details class="pt-1">
                                <summary class="cursor-pointer text-[11px] font-semibold text-slate-500 hover:text-slate-700">Show Authenticator URI (otpauth://)</summary>
                                <div class="mt-1.5 p-2 rounded-lg bg-white border border-slate-200 font-mono text-[11px] text-slate-600 break-all select-all">{{ $pendingOtpAuthUri }}</div>
                            </details>
                        @endif
                    </div>

                    <div class="pt-3 border-t border-slate-200 space-y-3">
                        <div class="font-bold text-slate-900">Step 2: Verify 6-Digit Code to Activate 2FA</div>
                        <p class="text-slate-600">Two-Factor Authentication remains inactive until you confirm a valid 6-digit code generated by your app.</p>

                        <form method="POST" action="{{ route('profile.two-factor.confirm') }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">6-Digit Authenticator Code <span class="text-rose-600">*</span></label>
                                <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="123456" required autofocus class="w-44 px-3 py-2 rounded-lg border border-slate-300 bg-white font-mono text-sm tracking-widest text-slate-900">
                                @error('code')
                                    <p class="text-[11px] text-rose-600 font-medium mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <button type="submit" class="px-4 py-2 rounded-lg bg-[#D3003B] hover:bg-[#b50032] text-white font-semibold transition">
                                Verify & Enable 2FA
                            </button>
                        </form>

                        <form method="POST" action="{{ route('profile.two-factor') }}">
                            @csrf
                            <input type="hidden" name="cancel" value="1">
                            <button type="submit" class="text-[11px] font-semibold text-slate-500 hover:text-slate-800 underline">Cancel 2FA setup</button>
                        </form>
                    </div>
                </div>
            @endif

            @if($user->two_factor_enabled)
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <form method="POST" action="{{ route('profile.two-factor.recovery-codes') }}" class="space-y-2">
                            @csrf
                            <div class="font-semibold text-slate-800">Regenerate Recovery Codes</div>
                            <p class="text-[11px] text-slate-500">Generate a fresh set of 8 recovery codes and invalidate previous codes.</p>
                            <input type="password" name="current_password" required placeholder="Current password" autocomplete="current-password" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-900">
                            <button type="submit" class="px-3.5 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-800 font-semibold transition">
                                Regenerate Codes
                            </button>
                        </form>

                        <form method="POST" action="{{ route('profile.two-factor') }}" class="space-y-2">
                            @csrf
                            <div class="font-semibold text-rose-700">Disable Two-Factor Authentication</div>
                            <p class="text-[11px] text-slate-500">Confirm your current password to turn off 2FA on your account.</p>
                            <input type="password" name="current_password" required placeholder="Current password" autocomplete="current-password" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-900">
                            <button type="submit" class="px-3.5 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-semibold transition">
                                Disable 2FA
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            <div class="pt-4 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="font-semibold text-slate-900">Browser Session Security</div>
                    <div class="text-slate-500">Immediately revoke all other active browser sessions across devices.</div>
                </div>
                <form method="POST" action="{{ route('profile.revoke-sessions') }}">
                    @csrf
                    <button type="submit" class="px-3.5 py-2 rounded-lg border border-slate-300 bg-white font-semibold text-slate-800 hover:bg-slate-100 transition">Revoke Other Sessions</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Login Activity History -->
    <div class="lg:col-span-5">
        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-xs">
            <h2 class="text-sm font-bold text-slate-900 mb-4">Recent Login & Session Activity</h2>
            <div class="divide-y divide-slate-100 text-xs">
                @forelse($user->loginActivities as $act)
                    <div class="py-2.5 flex items-center justify-between gap-3">
                        <div>
                            <div class="font-semibold text-slate-800">{{ strtoupper(str_replace('_', ' ', $act->event_type)) }}</div>
                            <div class="text-[11px] text-slate-500">IP: {{ $act->ip_address }}</div>
                        </div>
                        <div class="text-[11px] text-slate-500 tabular-nums shrink-0">{{ $act->occurred_at?->format('d M Y, H:i') }}</div>
                    </div>
                @empty
                    <div class="py-4 text-slate-400">No login activity recorded yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
