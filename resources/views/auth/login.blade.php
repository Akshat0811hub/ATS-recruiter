<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Sign in — OrbiTouch ATS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-dvh items-center justify-center bg-[#f6f8fc] p-5 font-sans text-slate-800">
    <main class="w-full max-w-[1000px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:grid lg:grid-cols-2">
        <section class="flex flex-col justify-between border-b border-slate-200 bg-[#f0f5ff] p-8 lg:border-r lg:border-b-0 lg:p-12">
            <div>
                <div class="flex items-center gap-3"><span class="flex size-10 items-center justify-center rounded-xl bg-[#D3003B] text-sm font-bold text-white">OT</span><span class="text-xl font-semibold tracking-tight text-slate-950">OrbiTouch<span class="text-blue-700">.</span></span></div>
                <h1 class="mt-10 text-3xl font-semibold leading-tight tracking-tight text-slate-950">Good people.<br>Great possibilities.</h1>
                <p class="mt-4 max-w-sm text-base leading-relaxed text-slate-600">One simple workspace for your candidates, clients and every step of recruitment.</p>
                <div class="mt-8 hidden space-y-4 lg:block">
                    @foreach(['Find the right candidates', 'Keep interviews and follow-ups on track', 'Turn offers into successful joinings'] as $item)
                    <div class="flex items-center gap-3 text-sm text-slate-700"><span class="rounded-full bg-white p-1.5 text-blue-700"><x-icon name="checkmark" class="size-4" /></span>{{ $item }}</div>
                    @endforeach
                </div>
            </div>
            <p class="mt-10 text-xs text-slate-500">OrbiTouch HR & Recruitment Consultants</p>
        </section>
        <section class="p-8 lg:p-12">
            <div class="mb-8"><h2 class="text-2xl font-semibold tracking-tight text-slate-950">Welcome back</h2><p class="mt-2 text-sm text-slate-500">Sign in to your recruitment workspace.</p></div>
            @if(session('status'))<div role="status" class="flash mb-5 border-emerald-200 bg-emerald-50 text-emerald-800">{{ session('status') }}</div>@endif
            @if($errors->any())<div role="alert" class="flash mb-5 border-rose-200 bg-rose-50 text-rose-800">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('login.submit') }}" class="space-y-5">
                @csrf
                <div><label for="email" class="mb-2 block text-sm font-medium">Work email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="you@company.com" class="field"></div>
                <div x-data="{ showPassword: false }"><div class="mb-2 flex items-center justify-between"><label for="password" class="text-sm font-medium">Password</label><a href="{{ route('password.request') }}" class="text-sm text-blue-700 hover:underline">Forgot password?</a></div><div class="relative"><input id="password" :type="showPassword ? 'text' : 'password'" type="password" name="password" required autocomplete="current-password" class="field pr-16"><button type="button" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'" class="absolute inset-y-0 right-0 px-3 text-xs font-medium text-slate-500" x-text="showPassword ? 'Hide' : 'Show'">Show</button></div></div>
                <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" value="1" class="size-4 accent-blue-700">Keep me signed in</label>
                <button type="submit" class="btn-primary w-full">Sign in</button>
            </form>
            <p class="mt-7 border-t border-slate-100 pt-5 text-sm leading-relaxed text-slate-500">Need an account? Contact your OrbiTouch administrator for an invitation.</p>
        </section>
    </main>
</body>
</html>
