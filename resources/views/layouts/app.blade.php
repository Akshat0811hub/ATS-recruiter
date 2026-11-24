<!DOCTYPE html>
<html lang="en" class="{{ auth()->user()?->theme_preference === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'OrbiTouch ATS') — OrbiTouch HR & Recruitment Consultants</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-sans antialiased selection:bg-[#D3003B] selection:text-white" x-data="orbitShell">
@php
    $currentUser = auth()->user();
    $unreadNotificationsCount = $currentUser
        ? \App\Models\AtsNotification::where('notifiable_type', \App\Models\User::class)->where('notifiable_id', $currentUser->id)->whereNull('read_at')->count()
        : 0;
@endphp

<div class="min-h-screen flex flex-col lg:flex-row">
    <!-- Collapsible Enterprise Sidebar -->
    <aside :class="sidebarCollapsed ? 'lg:w-20' : 'lg:w-68'"
           class="bg-[#0B1528] text-slate-200 flex-shrink-0 border-r border-slate-800/80 transition-all duration-200 flex flex-col justify-between z-30">
        <div>
            <!-- Brand Header -->
            <div class="h-16 px-4 flex items-center justify-between border-b border-slate-800/80">
                <a href="{{ $currentUser?->isClient() ? route('portal.dashboard') : route('dashboard') }}" class="flex items-center gap-3 overflow-hidden">
                    <div class="w-9 h-9 rounded-lg bg-[#D3003B] flex items-center justify-center text-white font-bold tracking-tight shadow-sm flex-shrink-0">
                        OT
                    </div>
                    <div x-show="!sidebarCollapsed" class="leading-tight truncate">
                        <div class="font-semibold text-white text-sm tracking-tight">OrbiTouch ATS</div>
                        <div class="text-[11px] text-slate-400 truncate">OrbiTouch HR Consultants</div>
                    </div>
                </a>
                <button type="button" @click="sidebarCollapsed = !sidebarCollapsed" class="hidden lg:inline-flex p-1.5 rounded-md text-slate-400 hover:text-white hover:bg-slate-800/60" title="Toggle Sidebar">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
                </button>
            </div>

            <!-- Navigation Sections -->
            <nav class="p-3 space-y-5 overflow-y-auto max-h-[calc(100vh-8rem)] text-xs">
                @if($currentUser && $currentUser->isClient())
                    <!-- Client Portal Navigation -->
                    <div>
                        <div x-show="!sidebarCollapsed" class="px-2.5 mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Client Portal</div>
                        <div class="space-y-0.5">
                            <a href="{{ route('portal.dashboard') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('portal.dashboard') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <span class="w-2 h-2 rounded-full bg-current"></span>
                                <span x-show="!sidebarCollapsed">Client Dashboard</span>
                            </a>
                            <a href="{{ route('portal.compare') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('portal.compare') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <span class="w-2 h-2 rounded-full bg-current"></span>
                                <span x-show="!sidebarCollapsed">Compare Candidates</span>
                            </a>
                        </div>
                    </div>
                @elseif($currentUser)
                    <!-- 1. WORKSPACE -->
                    <div>
                        <div x-show="!sidebarCollapsed" class="px-2.5 mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Workspace</div>
                        <div class="space-y-0.5">
                            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('dashboard') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                <span x-show="!sidebarCollapsed">Overview Dashboard</span>
                            </a>
                            <a href="{{ route('my-work') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('my-work') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                <span x-show="!sidebarCollapsed">My Work</span>
                            </a>
                            <a href="{{ route('tasks.index') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('tasks.*') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span x-show="!sidebarCollapsed">Tasks and Follow-ups</span>
                            </a>
                            <a href="{{ route('calendar') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('calendar') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span x-show="!sidebarCollapsed">Calendar</span>
                            </a>
                        </div>
                    </div>

                    <!-- 2. RECRUITMENT -->
                    <div>
                        <div x-show="!sidebarCollapsed" class="px-2.5 mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Recruitment</div>
                        <div class="space-y-0.5">
                            <a href="{{ route('clients.index') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('clients.*') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                <span x-show="!sidebarCollapsed">Clients</span>
                            </a>
                            <a href="{{ route('jobs.index') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('jobs.*') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <span x-show="!sidebarCollapsed">Job Requirements</span>
                            </a>
                            <a href="{{ route('candidates.index') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('candidates.*') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span x-show="!sidebarCollapsed">Candidates</span>
                            </a>
                            <a href="{{ route('applications.index') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('applications.*') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span x-show="!sidebarCollapsed">Candidate Applications</span>
                            </a>
                            <a href="{{ route('pipeline.index') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('pipeline.*') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
                                <span x-show="!sidebarCollapsed">Recruitment Pipeline</span>
                            </a>
                            <a href="{{ route('submissions.index') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('submissions.*') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                <span x-show="!sidebarCollapsed">Profile Submissions</span>
                            </a>
                            <a href="{{ route('interviews.index') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('interviews.*') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                <span x-show="!sidebarCollapsed">Interviews</span>
                            </a>
                            <a href="{{ route('offers.index') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('offers.*') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span x-show="!sidebarCollapsed">Offers</span>
                            </a>
                            <a href="{{ route('joinings.index') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('joinings.*') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <span x-show="!sidebarCollapsed">Joining and Placements</span>
                            </a>
                        </div>
                    </div>

                    <!-- 3. MANAGEMENT -->
                    <div>
                        <div x-show="!sidebarCollapsed" class="px-2.5 mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Management</div>
                        <div class="space-y-0.5">
                            @if($currentUser->hasRole(['super_admin', 'manager', 'team_leader']))
                            <a href="{{ route('admin.teams') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('admin.teams') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                <span x-show="!sidebarCollapsed">Team Management</span>
                            </a>
                            @endif
                            <a href="{{ route('management.recruiter-performance') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('management.recruiter-performance') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span x-show="!sidebarCollapsed">Recruiter Performance</span>
                            </a>
                            <a href="{{ route('management.client-performance') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('management.client-performance') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/></svg>
                                <span x-show="!sidebarCollapsed">Client Performance</span>
                            </a>
                            <a href="{{ route('reports.index') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('reports.*') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span x-show="!sidebarCollapsed">Reports and Analytics</span>
                            </a>
                            <a href="{{ route('notifications.index') }}" class="flex items-center justify-between px-2.5 py-2 rounded-md {{ request()->routeIs('notifications.*') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <span class="flex items-center gap-2.5">
                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                    <span x-show="!sidebarCollapsed">Notifications</span>
                                </span>
                                @if($unreadNotificationsCount > 0)
                                    <span x-show="!sidebarCollapsed" class="px-1.5 py-0.5 text-[10px] rounded-full bg-[#D3003B] text-white font-semibold tabular-nums">{{ $unreadNotificationsCount }}</span>
                                @endif
                            </a>
                            @if($currentUser->isSuperAdmin())
                            <a href="{{ route('admin.audit-logs') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('admin.audit-logs') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                <span x-show="!sidebarCollapsed">Audit Logs</span>
                            </a>
                            @endif
                        </div>
                    </div>

                    <!-- 4. ADMINISTRATION (Super Admin Only) -->
                    @if($currentUser->isSuperAdmin())
                    <div>
                        <div x-show="!sidebarCollapsed" class="px-2.5 mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Administration</div>
                        <div class="space-y-0.5">
                            <a href="{{ route('admin.users') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('admin.users') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                <span x-show="!sidebarCollapsed">User Management</span>
                            </a>
                            <a href="{{ route('admin.roles') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('admin.roles') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                <span x-show="!sidebarCollapsed">Roles and Permissions</span>
                            </a>
                            <a href="{{ route('admin.settings') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('admin.settings') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                <span x-show="!sidebarCollapsed">Recruitment & General Settings</span>
                            </a>
                            <a href="{{ route('admin.email-templates') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('admin.email-templates') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                <span x-show="!sidebarCollapsed">Email Templates</span>
                            </a>
                            <a href="{{ route('admin.custom-fields') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('admin.custom-fields') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                <span x-show="!sidebarCollapsed">Custom Fields</span>
                            </a>
                            <a href="{{ route('admin.storage') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('admin.storage') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                <span x-show="!sidebarCollapsed">Storage Management</span>
                            </a>
                            <a href="{{ route('admin.backups') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('admin.backups') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                <span x-show="!sidebarCollapsed">Backup Management</span>
                            </a>
                            <a href="{{ route('admin.recovery') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('admin.recovery') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                <span x-show="!sidebarCollapsed">Data Recovery</span>
                            </a>
                            <a href="{{ route('admin.system-health') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-md {{ request()->routeIs('admin.system-health') ? 'bg-[#D3003B] text-white font-medium' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                <span x-show="!sidebarCollapsed">System Health</span>
                            </a>
                        </div>
                    </div>
                    @endif
                @endif
            </nav>
        </div>

        <!-- User Footer -->
        @if($currentUser)
        <div class="p-3 border-t border-slate-800/80 bg-[#081020]">
            <div class="flex items-center justify-between gap-2">
                <a href="{{ route('profile.show') }}" class="flex items-center gap-2.5 truncate">
                    <div class="w-8 h-8 rounded-full bg-blue-600/30 border border-blue-400/40 flex items-center justify-center text-xs font-semibold text-white flex-shrink-0">
                        {{ strtoupper(substr($currentUser->name, 0, 2)) }}
                    </div>
                    <div x-show="!sidebarCollapsed" class="truncate">
                        <div class="text-xs font-medium text-white truncate">{{ $currentUser->name }}</div>
                        <div class="text-[10px] text-slate-400 uppercase tracking-wider">{{ $currentUser->role?->name ?? 'Staff' }}</div>
                    </div>
                </a>
                <form method="POST" action="{{ route('logout') }}" x-show="!sidebarCollapsed">
                    @csrf
                    <button type="submit" class="p-1.5 rounded text-slate-400 hover:text-rose-400 hover:bg-slate-800" title="Sign Out">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </button>
                </form>
            </div>
        </div>
        @endif
    </aside>

    <!-- Main Workspace Column -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Top Enterprise Bar -->
        <header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200/80 dark:border-slate-800 px-6 flex items-center justify-between gap-4 sticky top-0 z-20">
            <div class="flex items-center gap-3 min-w-0">
                <div>
                    <h1 class="text-base font-semibold text-slate-900 dark:text-white tracking-tight truncate">@yield('page_title', 'OrbiTouch Enterprise Workspace')</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">@yield('page_subtitle', 'OrbiTouch HR & Recruitment Consultants • IST (Asia/Kolkata)')</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <!-- Global Command Palette Trigger (Ctrl+K) -->
                <button type="button" @click="commandPaletteOpen = true; $nextTick(() => $refs.globalSearchInput?.focus())"
                        class="flex items-center gap-3 px-3 py-1.5 text-xs text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200/70 rounded-lg border border-slate-200/80 dark:border-slate-700 transition">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <span class="hidden sm:inline">Search candidates, jobs, phones, skills...</span>
                    <kbd class="px-1.5 py-0.5 text-[10px] font-mono bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 rounded border border-slate-300 dark:border-slate-700">Ctrl+K</kbd>
                </button>

                @if($currentUser && !$currentUser->isClient())
                <!-- Quick Action Menu -->
                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#D3003B] hover:bg-[#b50032] text-white shadow-xs transition">
                        <span>+ Quick Action</span>
                    </button>
                    <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 mt-2 w-52 bg-white dark:bg-slate-900 rounded-xl shadow-lg border border-slate-200 dark:border-slate-800 py-1.5 text-xs z-40">
                        <a href="{{ route('candidates.index', ['create' => 1]) }}" class="block px-3.5 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">+ Add Candidate Profile</a>
                        <a href="{{ route('jobs.index', ['create' => 1]) }}" class="block px-3.5 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">+ Create Job Requirement</a>
                        <a href="{{ route('pipeline.index') }}" class="block px-3.5 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">Open Kanban Pipeline</a>
                        <a href="{{ route('interviews.index') }}" class="block px-3.5 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">Schedule Interview Round</a>
                        <a href="{{ route('tasks.index') }}" class="block px-3.5 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">Add Follow-up Task</a>
                    </div>
                </div>
                @endif

                <!-- Theme Toggle -->
                <button type="button" @click="toggleTheme()" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" title="Toggle Dark/Light Theme">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </button>

                <!-- Notifications Bell -->
                <a href="{{ route('notifications.index') }}" class="relative p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" title="Notification Centre">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    @if($unreadNotificationsCount > 0)
                        <span class="absolute top-1 right-1 w-2 h-2 rounded-full bg-[#D3003B]"></span>
                    @endif
                </a>
            </div>
        </header>

        <!-- Flash Feedback Alerts -->
        <div class="px-6 pt-4 space-y-2">
            @if(session('success'))
                <div class="flex items-center justify-between px-4 py-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs font-medium">
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            @if(session('status'))
                <div class="px-4 py-3 rounded-lg bg-blue-50 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-200 text-xs font-medium">
                    {{ session('status') }}
                </div>
            @endif
            @if(session('duplicate_warning'))
                <div class="px-4 py-3 rounded-lg bg-amber-50 dark:bg-amber-950/60 border border-amber-300 dark:border-amber-700 text-amber-900 dark:text-amber-200 text-xs font-semibold">
                    ⚠️ {{ session('duplicate_warning') }}
                </div>
            @endif
            @if($errors->any())
                <div class="px-4 py-3 rounded-lg bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-xs">
                    <div class="font-semibold mb-1">Please resolve the following validation or workflow issue(s):</div>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <!-- Page Content -->
        <main class="p-6 flex-1">
            @yield('content')
        </main>

        <footer class="px-6 py-3 border-t border-slate-200/70 dark:border-slate-800 text-[11px] text-slate-400 flex items-center justify-between">
            <span>OrbiTouch ATS • Orbitouch Outsourcing Private Limited</span>
            <span>Timezone: Asia/Kolkata (IST) • Default Currency: INR</span>
        </footer>
    </div>
</div>

<!-- Global Command Palette Modal (Ctrl+K) -->
<div x-show="commandPaletteOpen" x-cloak class="fixed inset-0 z-50 flex items-start justify-center pt-20 bg-slate-950/60 backdrop-blur-xs">
    <div @click.away="commandPaletteOpen = false" class="w-full max-w-xl bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
        <div class="p-3.5 border-b border-slate-200 dark:border-slate-800 flex items-center gap-3">
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" x-ref="globalSearchInput" x-model="searchQuery" @input.debounce.300ms="performGlobalSearch()"
                   placeholder="Search by Candidate Name, ID, Phone, Email, Skill, Job ID, Client..."
                   class="w-full text-sm bg-transparent focus:outline-none text-slate-900 dark:text-white">
            <button type="button" @click="commandPaletteOpen = false" class="text-xs text-slate-400 hover:text-slate-600">ESC</button>
        </div>
        <div class="max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800 text-xs">
            <template x-if="searchLoading">
                <div class="p-4 text-center text-slate-400">Searching OrbiTouch database...</div>
            </template>
            <template x-for="item in searchResults" :key="item.code + item.url">
                <a :href="item.url" class="flex items-center justify-between px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition">
                    <div>
                        <div class="font-semibold text-slate-900 dark:text-white">
                            <span class="text-[#D3003B] font-mono mr-1.5" x-text="item.code"></span>
                            <span x-text="item.title"></span>
                        </div>
                        <div class="text-slate-500 text-[11px]" x-text="item.subtitle"></div>
                    </div>
                    <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-[10px] font-medium" x-text="item.type"></span>
                </a>
            </template>
            <template x-if="!searchLoading && searchQuery.length >= 2 && searchResults.length === 0">
                <div class="p-6 text-center text-slate-400">No matching records found for "<span x-text="searchQuery"></span>".</div>
            </template>
        </div>
    </div>
</div>

<!-- 360-Degree Candidate Side-Panel Preview Drawer -->
<div x-show="previewDrawerOpen" x-cloak class="fixed inset-0 z-50 flex justify-end bg-slate-950/50 backdrop-blur-xs">
    <div @click.away="previewDrawerOpen = false" class="w-full max-w-md bg-white dark:bg-slate-900 h-full shadow-2xl border-l border-slate-200 dark:border-slate-800 p-6 overflow-y-auto flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Candidate 360° Quick Preview</h3>
                <button type="button" @click="previewDrawerOpen = false" class="text-xs text-slate-400 hover:text-slate-600">Close ✕</button>
            </div>
            <template x-if="previewLoading">
                <div class="py-12 text-center text-xs text-slate-400">Loading candidate profile...</div>
            </template>
            <template x-if="previewCandidate">
                <div class="mt-4 space-y-4 text-xs">
                    <div>
                        <span class="font-mono text-[11px] text-[#D3003B] font-semibold" x-text="previewCandidate.candidate_code"></span>
                        <h4 class="text-base font-bold text-slate-900 dark:text-white" x-text="previewCandidate.full_name"></h4>
                        <p class="text-slate-500" x-text="(previewCandidate.current_designation || 'Professional') + ' • ' + (previewCandidate.current_employer || 'N/A')"></p>
                    </div>
                    <div class="grid grid-cols-2 gap-2.5 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200/70 dark:border-slate-800">
                        <div>
                            <div class="text-[10px] text-slate-400 uppercase">Mobile</div>
                            <a :href="'tel:' + previewCandidate.mobile_number" class="font-semibold text-blue-600 hover:underline" x-text="previewCandidate.mobile_number"></a>
                        </div>
                        <div>
                            <div class="text-[10px] text-slate-400 uppercase">Experience</div>
                            <div class="font-semibold tabular-nums" x-text="previewCandidate.total_experience + ' Yrs'"></div>
                        </div>
                        <div>
                            <div class="text-[10px] text-slate-400 uppercase">Current / Expected CTC</div>
                            <div class="font-semibold tabular-nums" x-text="(previewCandidate.current_ctc || 0) + ' → ' + (previewCandidate.expected_ctc || 0)"></div>
                        </div>
                        <div>
                            <div class="text-[10px] text-slate-400 uppercase">Notice Period</div>
                            <div class="font-semibold tabular-nums" x-text="previewCandidate.notice_period_days + ' Days'"></div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <a :href="'/candidates/' + previewCandidate.id" class="flex-1 text-center py-2 rounded-lg bg-[#0B1528] text-white font-semibold hover:bg-slate-800">Open Full 360° Profile</a>
                        <template x-if="previewCandidate.resume_download_url">
                            <a :href="previewCandidate.resume_download_url" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold hover:bg-slate-100">Resume CV</a>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
</body>
</html>
