<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password — OrbiTouch ATS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#0B1528] flex items-center justify-center p-6 font-sans">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-8">
        <h1 class="text-base font-bold text-slate-900">Forgot your password?</h1>
        <p class="text-xs text-slate-500 mt-1">Enter your registered OrbiTouch email to receive a password reset link.</p>
        @if(session('status'))
            <div class="mt-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs">{{ session('status') }}</div>
        @endif
        <form method="POST" action="{{ route('password.email') }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Email Address</label>
                <input type="email" name="email" required class="w-full px-3.5 py-2 text-xs rounded-lg border border-slate-300">
            </div>
            <button type="submit" class="w-full py-2.5 rounded-lg bg-[#D3003B] text-white font-semibold text-xs">Send Password Reset Link</button>
        </form>
        <div class="mt-4 text-center">
            <a href="{{ route('login') }}" class="text-xs text-[#1E40AF] hover:underline">← Back to Sign In</a>
        </div>
    </div>
</body>
</html>
