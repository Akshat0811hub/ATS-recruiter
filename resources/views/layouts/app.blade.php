<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Workspace') — OrbiTouch ATS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f6f8fc] font-sans text-slate-800 antialiased selection:bg-blue-100" x-data="orbitShell" :class="{ 'overflow-hidden': mobileMenuOpen || commandPaletteOpen || previewDrawerOpen }">
<a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:bg-white focus:p-3">Skip to content</a>
@php
    $currentUser = auth()->user();
    $homeRoute = $currentUser?->isClient() ? 'portal.dashboard' : 'dashboard';
    $navigation = $currentUser?->isClient() ? [
        'Client workspace' => [
            ['portal.dashboard', 'Dashboard', 'grid', null, 'portal.dashboard'],
            ['portal.compare', 'Compare candidates', 'users', null, 'portal.compare'],
            ['notifications.index', 'Notifications', 'bell', null, 'notifications.*'],
        ],
    ] : [
        'Workspace' => [
            ['dashboard', 'Overview', 'grid', null, 'dashboard'],
            ['my-work', 'My work', 'briefcase', null, 'my-work'],
            ['tasks.index', 'Tasks & follow-ups', 'check', 'tasks.manage', 'tasks.*'],
            ['calendar', 'Calendar', 'calendar', null, 'calendar'],
        ],
        'Recruitment' => [
            ['clients.index', 'Clients', 'building', 'clients.view', 'clients.*'],
            ['jobs.index', 'Jobs', 'briefcase', 'jobs.view', 'jobs.*'],
            ['candidates.index', 'Candidates', 'users', 'candidates.view', 'candidates.*'],
            ['applications.index', 'Applications', 'document', 'candidates.view', 'applications.*'],
            ['pipeline.index', 'Pipeline', 'pipeline', 'candidates.view', 'pipeline.*'],
            ['submissions.index', 'Submissions', 'send', 'submissions.create', 'submissions.*'],
            ['interviews.index', 'Interviews', 'calendar', 'interviews.schedule', 'interviews.*'],
            ['offers.index', 'Offers', 'document', 'offers.manage', 'offers.*'],
            ['joinings.index', 'Joinings', 'check', 'joinings.manage', 'joinings.*'],
        ],
        'Reports & team' => [
            ['management.recruiter-performance', 'Recruiter performance', 'chart', 'reports.view', 'management.recruiter-performance'],
            ['management.client-performance', 'Client performance', 'chart', 'reports.view', 'management.client-performance'],
            ['reports.index', 'Reports', 'chart', 'reports.view', 'reports.*'],
            ['admin.teams', 'Teams', 'users', 'teams.manage', 'admin.teams*'],
        ],
    ];
    if ($currentUser?->isSuperAdmin()) {
        $navigation['Administration'] = [
            ['admin.users', 'Users', 'users', null, 'admin.users*'],
            ['admin.roles', 'Roles & permissions', 'shield', null, 'admin.roles*'],
            ['admin.settings', 'Settings', 'settings', null, 'admin.settings*'],
            ['admin.email-templates', 'Email templates', 'mail', null, 'admin.email-templates*'],
            ['admin.custom-fields', 'Custom fields', 'document', null, 'admin.custom-fields*'],
            ['admin.audit-logs', 'Audit log', 'clock', null, 'admin.audit-logs'],
            ['admin.storage', 'Storage', 'document', null, 'admin.storage'],
            ['admin.backups', 'Backups', 'shield', null, 'admin.backups*'],
            ['admin.recovery', 'Recover records', 'document', null, 'admin.recovery*'],
            ['admin.system-health', 'System health', 'chart', null, 'admin.system-health'],
        ];
    }
@endphp
<div class="flex min-h-dvh">
    <div x-show="mobileMenuOpen" x-cloak @click="mobileMenuOpen = false" class="fixed inset-0 z-30 bg-slate-900/30 lg:hidden" aria-hidden="true"></div>
    <aside id="workspace-navigation" class="app-sidebar shrink-0" :class="{'max-lg:-translate-x-full': !mobileMenuOpen, 'max-lg:translate-x-0': mobileMenuOpen, 'is-collapsed': sidebarCollapsed}">
        <div class="flex h-20 shrink-0 items-center gap-3 border-b border-slate-100 px-5">
            <a href="{{ route($homeRoute) }}" class="flex min-w-0 items-center gap-3" aria-label="OrbiTouch ATS home">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#D3003B] text-sm font-bold text-white">OT</span>
                <span class="brand-text leading-tight"><strong class="block text-base tracking-tight text-slate-950">OrbiTouch<span class="text-blue-700">.</span></strong><span class="text-xs text-slate-500">Recruitment workspace</span></span>
            </a>
            <button type="button" @click="mobileMenuOpen = false" class="icon-button ml-auto lg:hidden" aria-label="Close navigation"><x-icon name="close" /></button>
        </div>
        <nav class="min-h-0 flex-1 overflow-y-auto px-3 pb-5" aria-label="Main navigation">
            @foreach($navigation as $group => $items)
                @php
                    $visibleItems = array_values(array_filter($items, fn ($item) => !$item[3] || $currentUser?->hasPermission($item[3])));
                    $collapsible = in_array($group, ['Reports & team', 'Administration']);
                    $groupActive = collect($visibleItems)->contains(fn ($item) => request()->routeIs($item[4]));
                @endphp
                @if(count($visibleItems))
                    @if($collapsible)
                    <details class="mt-3" :open="sidebarCollapsed || {{ $groupActive ? 'true' : 'false' }}" {{ $groupActive ? 'open' : '' }}>
                        <summary class="nav-section-title flex min-h-10 items-center justify-between rounded-lg px-3 text-xs font-semibold text-slate-500 hover:bg-slate-50" title="{{ $group }}"><span>{{ $group }}</span><x-icon name="chevron" class="size-4" /></summary>
                    @else
                        <div class="nav-heading">{{ $group }}</div>
                    @endif
                    <div class="space-y-1">
                        @foreach($visibleItems as [$routeName, $label, $icon, $permission, $pattern])
                            <a href="{{ route($routeName) }}" class="nav-link" title="{{ $label }}" @if(request()->routeIs($pattern)) aria-current="page" @endif @click="mobileMenuOpen = false"><x-icon :name="$icon" /><span class="nav-label">{{ $label }}</span></a>
                        @endforeach
                    </div>
                    @if($collapsible)</details>@endif
                @endif
            @endforeach
        </nav>
        <div class="border-t border-slate-200 p-3">
            <a href="{{ route('profile.show') }}" class="nav-link" title="My profile"><span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-semibold text-blue-800">{{ strtoupper(mb_substr($currentUser?->name ?? 'U', 0, 1)) }}</span><span class="nav-label min-w-0"><span class="block truncate text-sm font-semibold">{{ $currentUser?->name }}</span><span class="block text-xs font-normal text-slate-500">{{ $currentUser?->role?->name ?? 'Account' }}</span></span></a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link w-full" type="submit" title="Sign out"><x-icon name="logout" /><span class="nav-label">Sign out</span></button></form>
        </div>
    </aside>
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="app-topbar sticky top-0 z-20 flex h-20 items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-4 sm:px-7 lg:px-8">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" @click="mobileMenuOpen = !mobileMenuOpen" class="icon-button lg:hidden" aria-label="Open navigation" :aria-expanded="mobileMenuOpen" aria-controls="workspace-navigation"><x-icon name="menu" /></button>
                <button type="button" @click="sidebarCollapsed = !sidebarCollapsed" class="icon-button hidden lg:inline-flex" aria-label="Collapse or expand sidebar" :aria-expanded="!sidebarCollapsed"><x-icon name="panel" /></button>
                <nav class="hidden items-center gap-2 text-sm text-slate-500 sm:flex" aria-label="Breadcrumb"><a href="{{ route($homeRoute) }}" class="hover:text-blue-700">Workspace</a><span aria-hidden="true">/</span><span class="max-w-44 truncate text-slate-800">@yield('title', 'Overview')</span></nav>
            </div>
            <div class="flex items-center gap-2 sm:gap-3">
                <button type="button" @click="openSearch()" class="flex min-h-10 items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-500" aria-label="Search workspace"><x-icon name="search" class="size-4" /><span class="hidden xl:inline">Search workspace…</span><kbd class="hidden rounded border border-slate-200 bg-white px-1.5 text-xs sm:inline">Ctrl K</kbd></button>
                <a href="{{ route('notifications.index') }}" class="icon-button relative" aria-label="Notifications{{ $unreadNotificationsCount ? ', '.$unreadNotificationsCount.' unread' : '' }}"><x-icon name="bell" />@if($unreadNotificationsCount)<span class="absolute right-1 top-1 size-2 rounded-full bg-[#D3003B]"></span>@endif</a>
                @if($currentUser && !$currentUser->isClient())
                <div class="relative" @click.outside="quickCreateOpen = false">
                    <button type="button" @click="quickCreateOpen = !quickCreateOpen" class="btn-primary" :aria-expanded="quickCreateOpen" aria-controls="quick-add-menu"><x-icon name="plus" class="size-4" /><span class="hidden sm:inline">Add new</span><span class="sm:hidden">New</span></button>
                    <div id="quick-add-menu" x-show="quickCreateOpen" x-cloak class="absolute right-0 z-40 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                        @if($currentUser->hasPermission('candidates.create'))<a href="{{ route('candidates.index', ['create' => 1]) }}" class="nav-link"><x-icon name="users" />Candidate</a>@endif
                        @if($currentUser->hasPermission('jobs.create'))<a href="{{ route('jobs.index', ['create' => 1]) }}" class="nav-link"><x-icon name="briefcase" />Job requirement</a>@endif
                        @if($currentUser->hasPermission('clients.manage'))<a href="{{ route('clients.index', ['create' => 1]) }}" class="nav-link"><x-icon name="building" />Client</a>@endif
                        @if($currentUser->hasPermission('tasks.manage'))<a href="{{ route('tasks.index', ['create' => 1]) }}" class="nav-link"><x-icon name="check" />Follow-up task</a>@endif
                    </div>
                </div>
                @endif
            </div>
        </header>
        <main id="main-content" class="app-content" tabindex="-1">
            <div class="mb-7 flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0"><h1 class="text-2xl font-semibold tracking-tight text-slate-950">@yield('page_title', 'Your workspace')</h1><p class="mt-1.5 max-w-3xl text-sm leading-relaxed text-slate-500">@yield('page_subtitle', 'Your recruitment work, all in one place.')</p></div>
                @yield('page_actions')
            </div>
            <div class="space-y-3" aria-live="polite">
                @if(session('success'))<div class="flash mb-5 border-emerald-200 bg-emerald-50 text-emerald-800" role="status">{{ session('success') }}</div>@endif
                @if(session('status'))<div class="flash mb-5 border-blue-200 bg-blue-50 text-blue-800" role="status">{{ session('status') }}</div>@endif
                @if(session('duplicate_warning'))<div class="flash mb-5 border-amber-200 bg-amber-50 text-amber-900" role="alert">{{ session('duplicate_warning') }}</div>@endif
                @if($errors->any())
                <div class="flash mb-5 border-rose-200 bg-rose-50 text-rose-800" role="alert"><p class="mb-1 font-semibold">Please check these details and try again.</p><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
            </div>
            @yield('content')
        </main>
        <footer class="app-footer flex flex-wrap justify-between gap-2 border-t border-slate-200 px-4 py-4 text-xs text-slate-500 sm:px-8"><span>OrbiTouch HR & Recruitment Consultants</span><span>India Standard Time · INR</span></footer>
    </div>
</div>
<div x-show="commandPaletteOpen" x-cloak class="dialog-backdrop" @click.self="closeSearch()" @keydown.escape.stop="closeSearch()">
    <section role="dialog" aria-modal="true" aria-label="Search workspace" class="w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl" data-focus-dialog>
        <div class="flex items-center gap-3 border-b border-slate-200 p-4"><x-icon name="search" class="text-slate-400" /><input x-ref="globalSearchInput" x-model="searchQuery" @input.debounce.250ms="performGlobalSearch()" type="search" aria-label="Search candidates, jobs and clients" placeholder="Search candidates, jobs and clients…" class="min-w-0 flex-1 bg-transparent p-2 text-base outline-none"><button type="button" @click="closeSearch()" class="icon-button" aria-label="Close search"><x-icon name="close" /></button></div>
        <div class="max-h-[60vh] overflow-y-auto p-2" aria-live="polite" :aria-busy="searchLoading">
            <p x-show="searchLoading" class="empty-state">Searching…</p>
            <p x-show="searchError" x-text="searchError" role="alert" class="p-4 text-sm text-rose-700"></p>
            <p x-show="!searchLoading && !searchError && searchQuery.trim().length < 2" class="empty-state">Type at least 2 characters. Try a name, phone number, skill or job title.</p>
            <template x-for="item in searchResults" :key="item.code + item.url"><a :href="item.url" class="flex items-center justify-between gap-3 rounded-lg p-3 hover:bg-blue-50"><div class="min-w-0"><div class="flex items-center gap-2"><span x-show="item.code" class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-slate-700" x-text="item.code"></span><span class="truncate font-medium text-slate-900" x-text="item.title"></span></div><p class="mt-1 truncate text-xs text-slate-500" x-text="item.subtitle"></p></div><span class="shrink-0 rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600" x-text="item.type"></span></a></template>
            <p x-show="!searchLoading && !searchError && searchQuery.trim().length >= 2 && !searchResults.length" class="empty-state">No results found. Try a different name or keyword.</p>
        </div>
    </section>
</div>
<div x-show="previewDrawerOpen" x-cloak class="fixed inset-0 z-50 flex justify-end bg-slate-900/30" @click.self="closePreview()" @keydown.escape.stop="closePreview()">
    <section role="dialog" aria-modal="true" aria-label="Candidate preview" data-focus-dialog class="h-dvh w-full max-w-md overflow-y-auto border-l border-slate-200 bg-white p-6 shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 pb-4"><h2 class="text-lg font-semibold">Candidate preview</h2><button type="button" @click="closePreview()" class="icon-button" aria-label="Close preview"><x-icon name="close" /></button></div>
        <p x-show="previewLoading" class="empty-state">Loading candidate…</p>
        <p x-show="previewError" x-text="previewError" class="mt-5 text-sm text-rose-700" role="alert"></p>
        <template x-if="previewCandidate"><div class="space-y-6 pt-6">
            <div><span class="text-xs font-medium text-slate-500" x-text="previewCandidate.candidate_code"></span><h3 class="mt-1 text-2xl font-semibold text-slate-950" x-text="previewCandidate.full_name"></h3><p class="mt-2 text-sm text-slate-500" x-text="[previewCandidate.current_designation, previewCandidate.current_employer].filter(Boolean).join(' · ') || 'Role not added yet'"></p></div>
            <dl class="grid grid-cols-2 gap-5 text-sm"><div><dt class="text-slate-500">Phone</dt><dd class="mt-1"><a :href="'tel:' + previewCandidate.mobile_number" class="font-medium text-blue-700" x-text="previewCandidate.mobile_number"></a></dd></div><div><dt class="text-slate-500">Experience</dt><dd class="mt-1 font-medium" x-text="previewCandidate.total_experience + ' years'"></dd></div><div><dt class="text-slate-500">Current annual CTC</dt><dd class="mt-1 font-medium" x-text="formatMoney(previewCandidate.current_ctc, previewCandidate.currency)"></dd></div><div><dt class="text-slate-500">Expected annual CTC</dt><dd class="mt-1 font-medium" x-text="formatMoney(previewCandidate.expected_ctc, previewCandidate.currency)"></dd></div><div><dt class="text-slate-500">Notice period</dt><dd class="mt-1 font-medium" x-text="previewCandidate.notice_period_days === null ? 'Not provided' : previewCandidate.notice_period_days + ' days'"></dd></div><div><dt class="text-slate-500">Recruiter</dt><dd class="mt-1 font-medium" x-text="previewCandidate.recruiter || 'Unassigned'"></dd></div></dl>
            <div class="flex flex-wrap gap-2"><template x-for="skill in previewCandidate.skills" :key="skill"><span class="rounded-md bg-blue-50 px-2 py-1 text-xs text-blue-800" x-text="skill"></span></template></div>
            <div class="flex flex-wrap gap-2"><a :href="previewCandidate.profile_url" class="btn-primary">View full profile</a><template x-if="previewCandidate.resume_download_url"><a :href="previewCandidate.resume_download_url" class="btn-secondary">Download resume</a></template></div>
        </div></template>
    </section>
</div>
</body>
</html>
