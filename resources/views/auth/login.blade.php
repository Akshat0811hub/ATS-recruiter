<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In — OrbiTouch ATS | OrbiTouch HR & Recruitment Consultants</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#0B1528] text-slate-100 flex items-center justify-center p-6 font-sans antialiased">
    <div class="w-full max-w-4xl grid grid-cols-1 lg:grid-cols-12 rounded-2xl overflow-hidden shadow-2xl border border-slate-800 bg-slate-900">
        <!-- Brand Left Panel -->
        <div class="lg:col-span-5 bg-gradient-to-b from-[#0B1528] to-[#13223E] p-8 flex flex-col justify-between border-b lg:border-b-0 lg:border-r border-slate-800">
            <div>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-[#D3003B] flex items-center justify-center text-white font-bold text-base shadow-md">OT</div>
                    <div>
                        <div class="font-bold text-white text-base tracking-tight">OrbiTouch ATS</div>
                        <div class="text-xs text-slate-400">Enterprise Recruitment CRM</div>
                    </div>
                </div>
                <h1 class="mt-8 text-xl font-bold text-white leading-snug">Centralised Recruitment Lifecycle & Placement Platform</h1>
                <p class="mt-3 text-xs text-slate-300 leading-relaxed">
                    Engineered for OrbiTouch HR & Recruitment Consultants (Orbitouch Outsourcing Private Limited). Manage client requirements, candidate sourcing, multi-round interviews, offers, and placements in one unified workspace.
                </p>
            </div>
            <div class="mt-8 pt-6 border-t border-slate-800/80 text-[11px] text-slate-400 space-y-1">
                <div>Authorized Employee & Client HR Portal</div>
                <div>Mon–Sat • 10:00 AM – 6:30 PM IST (Asia/Kolkata)</div>
            </div>
        </div>

        <!-- Sign-In Form Panel -->
        <div class="lg:col-span-7 bg-white text-slate-900 p-8 lg:p-10 flex flex-col justify-center">
            <h2 class="text-lg font-bold text-slate-900">Sign in to your account</h2>
            <p class="text-xs text-slate-500 mt-1">Enter your corporate email and password to access OrbiTouch ATS.</p>

            @if(session('status'))
                <div class="mt-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-medium">
                    {{ session('status') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mt-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-xs font-medium">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.submit') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-700 mb-1">Corporate Email Address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                           placeholder="name@orbitouch-hr.com"
                           class="w-full px-3.5 py-2.5 text-xs rounded-lg border border-slate-300 focus:border-[#1E40AF] focus:outline-none">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="password" class="block text-xs font-semibold text-slate-700">Password</label>
                        <a href="{{ route('password.request') }}" class="text-xs text-[#1E40AF] hover:underline">Forgot password?</a>
                    </div>
                    <input id="password" type="password" name="password" required
                           class="w-full px-3.5 py-2.5 text-xs rounded-lg border border-slate-300 focus:border-[#1E40AF] focus:outline-none">
                </div>

                <div class="flex items-center justify-between text-xs">
                    <label class="inline-flex items-center gap-2 text-slate-600">
                        <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
                        <span>Remember this session</span>
                    </label>
                </div>

                <button type="submit" class="w-full py-2.5 px-4 rounded-lg bg-[#D3003B] hover:bg-[#b50032] text-white font-semibold text-xs shadow-sm transition">
                    Sign In to OrbiTouch ATS
                </button>
            </form>
        </div>
    </div>
</body>
</html>
