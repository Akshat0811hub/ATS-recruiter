@extends('layouts.app')
@section('title', 'Offers')
@section('page_title', 'Offers')
@section('page_subtitle', 'Track offers, salary discussions and candidate acceptance.')

@section('content')
<div x-data="{ showOfferModal: {{ request()->boolean('create') || $errors->any() ? 'true' : 'false' }} }">
    <!-- Filter & Create Toolbar -->
    <div class="mb-5 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
        <form method="GET" action="{{ route('offers.index') }}" class="flex flex-wrap items-center gap-2.5">
            <select name="status" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                <option value="">All Offer Statuses</option>
                @foreach(\App\Models\Offer::STATUSES as $sk => $sl)
                    <option value="{{ $sk }}" {{ request('status') === $sk ? 'selected' : '' }}>{{ $sl }}</option>
                @endforeach
            </select>
            <select name="client_id" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                <option value="">All Clients</option>
                @foreach($clients as $cl)
                    <option value="{{ $cl->id }}" {{ request('client_id') == $cl->id ? 'selected' : '' }}>{{ $cl->company_name }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-[#0B1528] text-white font-semibold">Filter</button>
        </form>
        <button type="button" @click="showOfferModal = true" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">
            + Create / Update Offer Record
        </button>
    </div>

    <!-- Offers Table -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                    <tr>
                        <th class="px-4 py-3">Offer ID</th>
                        <th class="px-4 py-3">Candidate & Offered Designation</th>
                        <th class="px-4 py-3">Client & Job</th>
                        <th class="px-4 py-3 text-right">Offered → Negotiated CTC</th>
                        <th class="px-4 py-3">Expected Joining</th>
                        <th class="px-4 py-3">Status & Revisions</th>
                        <th class="px-4 py-3">Quick Update</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
                    @forelse($offers as $offer)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-mono font-bold text-[#D3003B]">{{ $offer->offer_code }}</td>
                            <td class="px-4 py-3">
                                @if($offer->candidate)
                                    <a href="{{ route('candidates.show', $offer->candidate) }}" class="font-bold text-slate-900 dark:text-white hover:text-[#D3003B]">{{ $offer->candidate->full_name }}</a>
                                @endif
                                <div class="text-[11px] text-slate-500">{{ $offer->offered_designation }}</div>
                                @if($offer->offer_letter_storage_id)
                                    <a href="{{ route('documents.download', $offer->offer_letter_storage_id) }}" class="text-[10px] text-[#1E40AF] font-semibold hover:underline">Download Offer Letter PDF</a>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold">{{ $offer->client?->company_name }}</div>
                                <div class="text-[11px] text-slate-500">{{ $offer->jobRequirement?->job_code }} • {{ $offer->recruiter?->name }}</div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div>{{ number_format((float)$offer->offered_salary) }}</div>
                                <div class="font-bold text-emerald-600">→ {{ number_format((float)($offer->negotiated_salary ?? $offer->offered_salary)) }} {{ $offer->currency }}</div>
                            </td>
                            <td class="px-4 py-3 font-semibold">{{ $offer->expected_joining_date?->format('d M Y') ?? 'TBD' }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $offer->status === 'offer_accepted' ? 'bg-emerald-100 text-emerald-800' : (in_array($offer->status, ['offer_rejected', 'candidate_backed_out']) ? 'bg-rose-100 text-rose-800' : 'bg-blue-100 text-blue-800') }}">
                                    {{ \App\Models\Offer::STATUSES[$offer->status] ?? $offer->status }}
                                </span>
                                <div class="text-[10px] text-slate-400 mt-1">{{ $offer->revisions->count() }} revision(s)</div>
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('applications.offer.store', $offer->candidate_application_id) }}" class="flex items-center gap-1.5">
                                    @csrf
                                    <input type="hidden" name="selection_date" value="{{ $offer->selection_date?->toDateString() ?? now()->toDateString() }}">
                                    <input type="hidden" name="offered_designation" value="{{ $offer->offered_designation }}">
                                    <input type="hidden" name="offered_salary" value="{{ $offer->offered_salary }}">
                                    <input type="hidden" name="currency" value="{{ $offer->currency }}">
                                    <input type="hidden" name="expected_joining_date" value="{{ $offer->expected_joining_date?->toDateString() ?? now()->addDays(15)->toDateString() }}">
                                    <select name="status" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-[11px]">
                                        @foreach(\App\Models\Offer::STATUSES as $sk => $sl)
                                            <option value="{{ $sk }}" {{ $offer->status === $sk ? 'selected' : '' }}>{{ $sl }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="px-2.5 py-1 rounded bg-[#0B1528] text-white font-semibold text-[11px]">Update</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No offers recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-800">
            {{ $offers->links() }}
        </div>
    </div>

    <!-- Create / Update Offer Modal -->
    <div x-show="showOfferModal" x-cloak role="dialog" aria-modal="true" aria-label="offers form" @keydown.escape.window="showOfferModal = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 overflow-y-auto">
        <div @click.away="showOfferModal = false" x-data="{ selectedAppId: '{{ $selectedApplications->first()?->id ?? '' }}' }" class="w-full max-w-2xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 text-xs">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Create or Revise Candidate Offer</h3>
                <button type="button" @click="showOfferModal = false" class="text-slate-400">✕</button>
            </div>
            @if($selectedApplications->isEmpty())
                <div class="py-6 text-center text-slate-400">No candidate applications currently in Selected or Interview stages.</div>
            @else
                <form method="POST" :action="'/applications/' + selectedAppId + '/offer'" enctype="multipart/form-data" class="mt-4 space-y-3.5">
                    @csrf
                    <div>
                        <label class="block font-semibold mb-1">Candidate Application *</label>
                        <select x-model="selectedAppId" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            @foreach($selectedApplications as $sa)
                                <option value="{{ $sa->id }}">{{ $sa->application_code }} — {{ $sa->candidate?->full_name }} ({{ $sa->jobRequirement?->title }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block font-semibold mb-1">Offered Designation *</label>
                            <input type="text" name="offered_designation" required placeholder="Senior Software Architect" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Selection Date *</label>
                            <input type="date" name="selection_date" value="{{ now()->toDateString() }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Offered Annual Salary *</label>
                            <input type="number" name="offered_salary" value="2400000" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Negotiated Annual Salary</label>
                            <input type="number" name="negotiated_salary" value="2500000" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Currency *</label>
                            <input type="text" name="currency" value="INR" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Offer Status *</label>
                            <select name="status" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                                @foreach(\App\Models\Offer::STATUSES as $sk => $sl)
                                    <option value="{{ $sk }}">{{ $sl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Expected Joining Date</label>
                            <input type="date" name="expected_joining_date" value="{{ now()->addDays(15)->toDateString() }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Private Offer Letter File</label>
                            <input type="file" name="offer_letter_file" class="w-full text-[11px]">
                        </div>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Negotiation & Revision Notes</label>
                        <textarea name="negotiation_notes" rows="2" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                        <button type="button" @click="showOfferModal = false" class="px-3.5 py-2 rounded-lg border border-slate-300">Cancel</button>
                        <button type="submit" class="px-5 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">Save Offer</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
