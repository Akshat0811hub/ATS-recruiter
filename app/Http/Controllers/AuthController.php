<?php

namespace App\Http\Controllers;

use App\Models\LoginActivity;
use App\Models\User;
use App\Services\AuditService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactorService) {}

    public function showLogin()
    {
        if (Auth::check()) {
            return Auth::user()->isClient()
                ? redirect()->route('portal.dashboard')
                : redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower(trim($credentials['email']));
        $throttleKey = 'login:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            LoginActivity::create([
                'email' => $email,
                'event_type' => 'login_rate_limited',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'failure_reason' => "Rate limit exceeded ({$seconds}s remaining)",
                'occurred_at' => now(),
            ]);

            return back()->withErrors([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ])->onlyInput('email');
        }

        $user = User::where('email', $email)->first();

        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            return back()->withErrors([
                'email' => 'Account temporarily locked due to repeated failed login attempts. Try again after '.$user->locked_until->format('H:i').'.',
            ])->onlyInput('email');
        }

        if ($user && ! $user->is_active) {
            LoginActivity::create([
                'user_id' => $user->id,
                'email' => $email,
                'event_type' => 'login_blocked_inactive',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'failure_reason' => 'Account deactivated',
                'occurred_at' => now(),
            ]);

            return back()->withErrors([
                'email' => 'Your account is currently deactivated. Please contact the Super Admin.',
            ])->onlyInput('email');
        }

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 300);

            if ($user) {
                $attempts = $user->failed_login_attempts + 1;
                $user->update([
                    'failed_login_attempts' => $attempts,
                    'locked_until' => $attempts >= 5 ? now()->addMinutes(15) : null,
                ]);
            }

            LoginActivity::create([
                'user_id' => $user?->id,
                'email' => $email,
                'event_type' => 'login_failed',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'failure_reason' => 'Invalid credentials',
                'occurred_at' => now(),
            ]);

            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        LoginActivity::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'event_type' => 'login_success',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'occurred_at' => now(),
        ]);

        AuditService::log('auth.login', $user, $user->id);

        if ($user->two_factor_enabled) {
            $request->session()->put('two_factor_verified', false);

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->put('two_factor_verified', true);

        return $user->isClient()
            ? redirect()->intended(route('portal.dashboard'))
            : redirect()->intended(route('dashboard'));
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', strtolower(trim($data['email'])))->first();

        if ($user) {
            $plainToken = Str::random(48);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($plainToken), 'created_at' => now()]
            );
            AuditService::log('auth.password_reset_requested', $user, $user->id);
        }

        return back()->with('status', 'If that email exists in OrbiTouch ATS, a password reset link has been dispatched.');
    }

    public function showResetPassword(string $token, Request $request)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $data['email'])->first();
        if (! $record || ! Hash::check($data['token'], $record->token)) {
            return back()->withErrors(['email' => 'Invalid or expired password reset token.']);
        }

        $user = User::where('email', $data['email'])->firstOrFail();
        $user->update([
            'password' => Hash::make($data['password']),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
        AuditService::log('auth.password_reset_completed', $user, $user->id);

        return redirect()->route('login')->with('status', 'Password has been reset successfully. You may now sign in.');
    }

    public function showAcceptInvite(string $token)
    {
        $user = User::where('invitation_token', $token)->firstOrFail();

        return view('auth.accept-invite', compact('user', 'token'));
    }

    public function acceptInvite(Request $request, string $token)
    {
        $user = User::where('invitation_token', $token)->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? $user->phone,
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
            'invitation_token' => null,
            'invitation_accepted_at' => now(),
            'is_active' => true,
        ]);

        Auth::login($user);
        $request->session()->put('two_factor_verified', true);
        AuditService::log('auth.invitation_accepted', $user, $user->id);

        return $user->isClient()
            ? redirect()->route('portal.dashboard')->with('success', 'Welcome to the OrbiTouch HR Client Portal.')
            : redirect()->route('dashboard')->with('success', 'Account activated. Welcome to OrbiTouch ATS.');
    }

    public function showTwoFactorChallenge()
    {
        return view('auth.two-factor-challenge');
    }

    public function verifyTwoFactorChallenge(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = Auth::user();
        if (! $this->twoFactorService->verifyCode($user, $data['code'])) {
            return back()->withErrors(['code' => 'Invalid authentication code or recovery key.']);
        }

        $request->session()->put('two_factor_verified', true);
        LoginActivity::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'event_type' => '2fa_verified',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'occurred_at' => now(),
        ]);

        return $user->isClient()
            ? redirect()->route('portal.dashboard')
            : redirect()->route('dashboard');
    }

    public function profile()
    {
        $user = Auth::user()->load(['role', 'team', 'loginActivities' => fn ($q) => $q->latest('occurred_at')->limit(15)]);
        $currentTotpPreview = $user->two_factor_secret
            ? $this->twoFactorService->getCurrentTotpCode($user->two_factor_secret)
            : null;

        return view('auth.profile', compact('user', 'currentTotpPreview'));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'designation' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'string', 'max:64'],
            'theme_preference' => ['required', 'in:light,dark'],
            'current_password' => ['nullable', 'required_with:new_password', 'string'],
            'new_password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (! empty($data['new_password'])) {
            if (! Hash::check($data['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => 'Current password is incorrect.']);
            }
            $user->password = Hash::make($data['new_password']);
        }

        $user->fill([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'designation' => $data['designation'],
            'timezone' => $data['timezone'],
            'theme_preference' => $data['theme_preference'],
        ])->save();

        AuditService::log('user.profile_updated', $user, $user->id);

        return back()->with('success', 'Profile preferences updated successfully.');
    }

    public function toggleTwoFactor(Request $request)
    {
        $user = Auth::user();

        if (! $user->two_factor_enabled) {
            $secret = $this->twoFactorService->generateSecret();
            $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();
            $user->update([
                'two_factor_enabled' => true,
                'two_factor_secret' => $secret,
                'two_factor_recovery_codes' => json_encode($recoveryCodes),
                'two_factor_confirmed_at' => now(),
            ]);
            $request->session()->put('two_factor_verified', true);
            AuditService::log('auth.2fa_enabled', $user, $user->id);

            return back()->with('success', 'Two-Factor Authentication enabled. Store your recovery codes securely.');
        }

        if ($user->isSuperAdmin() && app()->environment('production') && filter_var(env('ATS_REQUIRE_SUPER_ADMIN_2FA', true), FILTER_VALIDATE_BOOLEAN)) {
            return back()->withErrors(['two_factor' => 'Two-Factor Authentication is mandatory for Super Admin accounts in production.']);
        }

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
        AuditService::log('auth.2fa_disabled', $user, $user->id);

        return back()->with('success', 'Two-Factor Authentication has been disabled.');
    }

    public function revokeOtherSessions(Request $request)
    {
        $user = Auth::user();
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        LoginActivity::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'event_type' => 'session_revoked',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'occurred_at' => now(),
        ]);

        AuditService::log('auth.sessions_revoked', $user, $user->id);

        return back()->with('success', 'All other active browser sessions have been revoked.');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            LoginActivity::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'event_type' => 'logout',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'occurred_at' => now(),
            ]);
            AuditService::log('auth.logout', $user, $user->id);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
