<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accept Invitation — OrbiTouch ATS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#0B1528] flex items-center justify-center p-6 font-sans">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-8">
        <h1 class="text-base font-bold text-slate-900">Accept OrbiTouch ATS Invitation</h1>
        <p class="text-xs text-slate-500 mt-1">Activate your account for <strong>{{ $user->email }}</strong></p>
        <form method="POST" action="{{ route('invite.accept', $token) }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Full Name</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full px-3.5 py-2 text-xs rounded-lg border border-slate-300">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Phone Number</label>
                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="w-full px-3.5 py-2 text-xs rounded-lg border border-slate-300">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Create Password</label>
                <input type="password" name="password" required class="w-full px-3.5 py-2 text-xs rounded-lg border border-slate-300">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Confirm Password</label>
                <input type="password" name="password_confirmation" required class="w-full px-3.5 py-2 text-xs rounded-lg border border-slate-300">
            </div>
            <button type="submit" class="w-full py-2.5 rounded-lg bg-[#D3003B] text-white font-semibold text-xs">Activate Account</button>
        </form>
    </div>
</body>
</html>
