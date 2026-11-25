<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set New Password — OrbiTouch ATS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f6f8fc] flex items-center justify-center p-6 font-sans">
    <div class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
        <h1 class="text-2xl font-semibold text-slate-900">Set New Password</h1>
        @if($errors->any())
            <div class="mt-3 p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-sm">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('password.update') }}" class="mt-5 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Email Address</label>
                <input type="email" name="email" value="{{ $email }}" required class="w-full px-3.5 py-2 text-sm rounded-lg border border-slate-300">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">New Password</label>
                <input type="password" name="password" required class="w-full px-3.5 py-2 text-sm rounded-lg border border-slate-300">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Confirm New Password</label>
                <input type="password" name="password_confirmation" required class="w-full px-3.5 py-2 text-sm rounded-lg border border-slate-300">
            </div>
            <button type="submit" class="w-full py-2.5 rounded-lg bg-[#D3003B] text-white font-semibold text-sm">Update Password</button>
        </form>
    </div>
</body>
</html>
