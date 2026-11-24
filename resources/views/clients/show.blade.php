@extends('layouts.app')
@section('title', $client->company_name . ' — Client Profile')
@section('page_title', $client->company_name . ' (' . $client->client_code . ')')
@section('page_subtitle', ($client->industry ?? 'Client Partner') . ' • Account Manager: ' . ($client->accountManager?->name ?? 'Unassigned'))

@section('content')
<!-- 10 Required Client Profile KPIs -->
<div class="grid grid-cols-2 sm:grid-cols-5 lg:grid-cols-10 gap-2.5 mb-6 text-xs">
    @foreach([
        ['Total Reqs', $kpis['total_requirements'], 'text-slate-900 dark:text-white'],
        ['Active Vacancies', $kpis['active_vacancies'], 'text-[#1E40AF]'],
        ['Submitted', $kpis['candidates_submitted'], 'text-slate-900 dark:text-white'],
        ['Shortlisted', $kpis['candidates_shortlisted'], 'text-blue-600'],
        ['Interviews', $kpis['interviews_scheduled'], 'text-indigo-600'],
        ['Selected', $kpis['candidates_selected'], 'text-emerald-600'],
        ['Offers', $kpis['offers_released'], 'text-purple-600'],
        ['Joined', $kpis['candidates_joined'], 'text-emerald-700'],
        ['Pending Feedback', $kpis['pending_feedback'], 'text-amber-600'],
        ['Closed Reqs', $kpis['closed_requirements'], 'text-slate-500'],
    ] as [$label, $val, $color])
        <div class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <div class="text-[10px] text-slate-400 font-medium truncate">{{ $label }}</div>
            <div class="mt-1 text-base font-bold tabular-nums {{ $color }}">{{ $val }}</div>
        </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 text-xs">
    <!-- Left Column: Commercial Terms, Contacts & Edit Form -->
    <div class="lg:col-span-5 space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Commercial & Agreement Details</h2>
                @if($client->agreement_storage_id)
                    <a href="{{ route('documents.download', $client->agreement_storage_id) }}" class="px-2.5 py-1 rounded bg-[#0B1528] text-white font-semibold text-[11px]">
                        Download Agreement
                    </a>
                @endif
            </div>
            <div class="grid grid-cols-2 gap-3 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60">
                <div><span class="text-slate-400 block text-[10px]">Placement Fee</span><strong>{{ $client->placement_fee_percentage }}% CTC</strong></div>
                <div><span class="text-slate-400 block text-[10px]">Replacement Window</span><strong>{{ $client->replacement_period_days }} Days</strong></div>
                <div><span class="text-slate-400 block text-[10px]">Payment Terms</span><strong>{{ $client->payment_terms_days }} Days</strong></div>
                <div><span class="text-slate-400 block text-[10px]">Locations</span><strong>{{ is_array($client->locations) ? implode(', ', $client->locations) : $client->city }}</strong></div>
            </div>
            @if($client->recruitment_terms)
                <div>
                    <div class="text-[10px] font-semibold uppercase text-slate-400">Recruitment Terms</div>
                    <p class="text-slate-600 dark:text-slate-300 mt-0.5">{{ $client->recruitment_terms }}</p>
                </div>
            @endif
            @if($client->internal_notes)
                <div class="p-3 rounded-lg bg-amber-50/70 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800">
                    <div class="text-[10px] font-bold uppercase text-amber-800 dark:text-amber-300">Confidential Internal Notes (Hidden from Client Portal)</div>
                    <p class="text-amber-900 dark:text-amber-200 mt-0.5">{{ $client->internal_notes }}</p>
                </div>
            @endif
        </div>

        <!-- HR Representatives under this Client Organisation -->
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">HR Contacts ({{ $client->contacts->count() }})</h2>
            <div class="divide-y divide-slate-100 dark:divide-slate-800 mb-4">
                @foreach($client->contacts as $contact)
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <div class="font-semibold text-slate-900 dark:text-white">{{ $contact->name }} @if($contact->is_primary)<span class="text-[10px] text-[#D3003B]">(Primary)</span>@endif</div>
                            <div class="text-[11px] text-slate-500">{{ $contact->designation }} • {{ $contact->email }} • {{ $contact->phone }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if(auth()->user()->hasRole(['super_admin', 'manager']))
                <form method="POST" action="{{ route('clients.contacts.store', $client) }}" class="pt-3 border-t border-slate-200 dark:border-slate-800 space-y-2.5">
                    @csrf
                    <div class="font-semibold text-slate-700 dark:text-slate-300">Add Additional HR Representative</div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="name" placeholder="Contact Name *" required class="px-2.5 py-1.5 rounded border border-slate-300 dark:border-slate-700 bg-transparent">
                        <input type="text" name="designation" placeholder="Designation" class="px-2.5 py-1.5 rounded border border-slate-300 dark:border-slate-700 bg-transparent">
                        <input type="email" name="email" placeholder="Email Address *" required class="px-2.5 py-1.5 rounded border border-slate-300 dark:border-slate-700 bg-transparent">
                        <input type="text" name="phone" placeholder="Phone Number" class="px-2.5 py-1.5 rounded border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div class="flex items-center justify-between">
                        <label class="inline-flex items-center gap-1.5 text-[11px]">
                            <input type="checkbox" name="create_portal_login" value="1" checked>
                            <span>Enable Client Portal Login</span>
                        </label>
                        <button type="submit" class="px-3 py-1.5 rounded bg-[#0B1528] text-white font-semibold">+ Add HR Contact</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <!-- Right Column: Client Job Requirements -->
    <div class="lg:col-span-7">
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Client Job Requirements ({{ $client->jobRequirements->count() }})</h2>
                <a href="{{ route('jobs.index', ['client_id' => $client->id]) }}" class="text-[#1E40AF] hover:underline">View All →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                        <tr>
                            <th class="px-4 py-2.5">Job ID</th>
                            <th class="px-4 py-2.5">Title & Location</th>
                            <th class="px-4 py-2.5 text-right">Openings</th>
                            <th class="px-4 py-2.5 text-right">Joined</th>
                            <th class="px-4 py-2.5 text-right">Remaining</th>
                            <th class="px-4 py-2.5">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
                        @forelse($client->jobRequirements as $job)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-mono font-semibold text-[#D3003B]">
                                    <a href="{{ route('jobs.show', $job) }}">{{ $job->job_code }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('jobs.show', $job) }}" class="font-semibold text-slate-900 dark:text-white hover:text-[#D3003B]">{{ $job->title }}</a>
                                    <div class="text-[11px] text-slate-500">{{ $job->location }} ({{ strtoupper($job->work_mode) }})</div>
                                </td>
                                <td class="px-4 py-3 text-right">{{ $job->openings_count }}</td>
                                <td class="px-4 py-3 text-right text-emerald-600 font-semibold">{{ $job->joined_count }}</td>
                                <td class="px-4 py-3 text-right font-bold text-[#1E40AF]">{{ $job->remaining_count }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-[10px] font-semibold uppercase">{{ str_replace('_', ' ', $job->status) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No job requirements created for this client yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
