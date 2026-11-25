<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Two-Factor Verification — OrbiTouch ATS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f6f8fc] flex items-center justify-center p-6 font-sans">
    <div class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
        <div class="w-10 h-10 rounded-xl bg-[#D3003B] text-white font-bold flex items-center justify-center mb-4">2FA</div>
        <h1 class="text-2xl font-semibold text-slate-900">Two-Factor Authentication</h1>
        <p class="text-sm text-slate-500 mt-1">Enter your 6-digit TOTP authenticator code or an unused recovery code.</p>
        @if($errors->any())
            <div class="mt-3 p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-sm">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('two-factor.verify') }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Authentication / Recovery Code</label>
                <input type="text" name="code" required autofocus placeholder="123456 or XXXXX-XXXXX"
                       class="w-full px-3.5 py-2.5 text-sm font-mono tracking-widest rounded-lg border border-slate-300">
            </div>
            <button type="submit" class="w-full py-2.5 rounded-lg bg-[#D3003B] text-white font-semibold text-sm">Verify & Continue</button>
        </form>
    </div>
</body>
</html>
