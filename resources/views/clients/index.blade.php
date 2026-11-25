@extends('layouts.app')
@section('title', 'Clients')
@section('page_title', 'Clients')
@section('page_subtitle', 'Manage companies, HR contacts and hiring requirements.')

@section('content')
<div x-data="{ showCreateModal: {{ request()->boolean('create') || $errors->any() ? 'true' : 'false' }} }">
    <!-- Filter & Action Toolbar -->
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 text-xs">
        <form method="GET" action="{{ route('clients.index') }}" class="flex flex-wrap items-center gap-2.5">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search Client ID, company, HR name..."
                   class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent w-64">
            <select name="status" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                <option value="">All Statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="onboarding" {{ request('status') === 'onboarding' ? 'selected' : '' }}>Onboarding</option>
                <option value="on_hold" {{ request('status') === 'on_hold' ? 'selected' : '' }}>On Hold</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
            <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-[#0B1528] text-white font-semibold">Search</button>
        </form>
        @if(auth()->user()->hasRole(['super_admin', 'manager']))
            <button type="button" @click="showCreateModal = true" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">
                + Add Client Organisation
            </button>
        @endif
    </div>

    <!-- Clients Data Table -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                    <tr>
                        <th class="px-4 py-3">Client ID</th>
                        <th class="px-4 py-3">Company & Industry</th>
                        <th class="px-4 py-3">Primary HR Contact</th>
                        <th class="px-4 py-3">Fee & Replacement</th>
                        <th class="px-4 py-3 text-right">Jobs</th>
                        <th class="px-4 py-3 text-right">Submissions</th>
                        <th class="px-4 py-3 text-right">Shortlisted</th>
                        <th class="px-4 py-3 text-right">Joined</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
                    @forelse($clients as $client)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-mono font-semibold text-[#D3003B]">
                                <a href="{{ route('clients.show', $client) }}">{{ $client->client_code }}</a>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('clients.show', $client) }}" class="font-bold text-slate-900 dark:text-white hover:text-[#D3003B]">{{ $client->company_name }}</a>
                                <div class="text-[11px] text-slate-500">{{ $client->industry }} • {{ $client->city }}, {{ $client->country }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800 dark:text-slate-200">{{ $client->primary_hr_name }}</div>
                                <div class="text-[11px] text-slate-500">{{ $client->primary_hr_phone }} • {{ $client->primary_hr_email }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold">{{ $client->placement_fee_percentage }}% CTC</div>
                                <div class="text-[11px] text-slate-500">{{ $client->replacement_period_days }}d warranty</div>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold">{{ $client->job_requirements_count }}</td>
                            <td class="px-4 py-3 text-right">{{ $client->client_submissions_count }}</td>
                            <td class="px-4 py-3 text-right text-blue-600 font-semibold">{{ $client->shortlisted_count }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600 font-bold">{{ $client->joined_count }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase {{ $client->status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $client->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center text-slate-400">No clients found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-800">
            {{ $clients->links() }}
        </div>
    </div>

    <!-- Create Client Modal -->
    @if(auth()->user()->hasRole(['super_admin', 'manager']))
    <div x-show="showCreateModal" x-cloak role="dialog" aria-modal="true" aria-label="clients form" @keydown.escape.window="showCreateModal = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 overflow-y-auto">
        <div @click.away="showCreateModal = false" class="w-full max-w-3xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Create New Client Organisation</h3>
                <button type="button" @click="showCreateModal = false" class="text-xs text-slate-400">✕</button>
            </div>
            <form method="POST" action="{{ route('clients.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4 text-xs">
                @csrf
                @if($errors->any())
                    <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-rose-800">
                        <p class="font-semibold">Please check the client details below:</p>
                        <ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <div class="sm:col-span-2">
                        <label class="block font-semibold mb-1">Company Name *</label>
                        <input type="text" name="company_name" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Industry</label>
                        <input type="text" name="industry" placeholder="FinTech / IT / Pharma" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">City</label>
                        <input type="text" name="city" value="Mumbai" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Country *</label>
                        <input type="text" name="country" value="India" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Locations (CSV)</label>
                        <input type="text" name="locations_csv" placeholder="Mumbai, Bengaluru, Pune" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Primary HR Contact Name *</label>
                        <input type="text" name="primary_hr_name" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">HR Designation</label>
                        <input type="text" name="primary_hr_designation" value="Head - Talent Acquisition" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">HR Phone *</label>
                        <input type="text" name="primary_hr_phone" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">HR Email *</label>
                        <input type="email" name="primary_hr_email" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Placement Fee (%)</label>
                        <input type="number" step="0.01" name="placement_fee_percentage" value="8.33" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Replacement Warranty (Days)</label>
                        <input type="number" name="replacement_period_days" value="90" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Payment Terms (Days)</label>
                        <input type="number" name="payment_terms_days" value="30" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Currency</label>
                        <input type="text" name="currency" value="INR" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            <option value="active">Active</option>
                            <option value="onboarding">Onboarding</option>
                            <option value="on_hold">On Hold</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block font-semibold mb-1">Private Client Agreement Document (PDF/DOCX)</label>
                        <input type="file" name="agreement_file" class="w-full text-xs">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block font-semibold mb-1">Recruitment Terms & Conditions</label>
                        <textarea name="recruitment_terms" rows="2" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Internal Confidential Notes (Hidden from Client)</label>
                        <textarea name="internal_notes" rows="2" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent"></textarea>
                    </div>
                </div>
                <div class="flex items-center justify-between pt-3 border-t border-slate-200 dark:border-slate-800">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="create_portal_user" value="1" checked>
                        <span>Provision isolated Client Portal login for Primary HR</span>
                    </label>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">Create Client</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
