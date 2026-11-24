@extends('layouts.app')
@section('title', 'Central Candidate Master Database')
@section('page_title', 'Central Candidate Master Database')
@section('page_subtitle', 'Searchable candidate vault with multi-signal duplicate detection, private resume versioning, and CSV import/export')

@section('content')
<div x-data="{
    showCreateModal: {{ request()->boolean('create') || session('duplicate_warning') ? 'true' : 'false' }},
    showImportModal: false
}">
    <!-- Filter & Action Toolbar -->
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 text-xs">
        <form method="GET" action="{{ route('candidates.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search Name, ID, Phone, Email, Skill, Company..."
                   class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent w-64">
            <input type="text" name="location" value="{{ request('location') }}" placeholder="Location"
                   class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent w-32">
            <select name="recruiter_id" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                <option value="">All Recruiters</option>
                @foreach($recruiters as $rec)
                    <option value="{{ $rec->id }}" {{ request('recruiter_id') == $rec->id ? 'selected' : '' }}>{{ $rec->name }}</option>
                @endforeach
            </select>
            <input type="number" name="max_notice_days" value="{{ request('max_notice_days') }}" placeholder="Max Notice (Days)"
                   class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent w-36">
            <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-[#0B1528] text-white font-semibold">Search</button>
        </form>

        <div class="flex items-center gap-2">
            @if(auth()->user()->hasRole(['super_admin', 'manager', 'team_leader']))
                <button type="button" @click="showImportModal = true" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold hover:bg-slate-100">
                    Bulk Import CSV
                </button>
            @endif
            @if(auth()->user()->hasRole(['super_admin', 'manager']))
                <a href="{{ route('candidates.export') }}" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold hover:bg-slate-100">
                    Export CSV
                </a>
            @endif
            <button type="button" @click="showCreateModal = true" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">
                + Add Candidate Profile
            </button>
        </div>
    </div>

    <!-- Candidate Master Table -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                    <tr>
                        <th class="px-4 py-3">Candidate ID</th>
                        <th class="px-4 py-3">Name & Contact</th>
                        <th class="px-4 py-3">Current Role & Employer</th>
                        <th class="px-4 py-3">Skills</th>
                        <th class="px-4 py-3 text-right">Exp / Notice</th>
                        <th class="px-4 py-3 text-right">Current / Expected CTC</th>
                        <th class="px-4 py-3">Owner & Stage</th>
                        <th class="px-4 py-3 text-right">Quick Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
                    @forelse($candidates as $cand)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-mono font-bold text-[#D3003B]">
                                <a href="{{ route('candidates.show', $cand) }}">{{ $cand->candidate_code }}</a>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('candidates.show', $cand) }}" class="font-bold text-slate-900 dark:text-white hover:text-[#D3003B]">{{ $cand->full_name }}</a>
                                <div class="text-[11px] text-slate-500">
                                    <a href="tel:{{ $cand->mobile_number }}" class="text-blue-600 hover:underline">{{ $cand->mobile_number }}</a>
                                    @if($cand->email) • {{ $cand->email }} @endif
                                </div>
                                <div class="text-[10px] text-slate-400">{{ $cand->current_location }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $cand->current_designation ?? 'N/A' }}</div>
                                <div class="text-[11px] text-slate-500">{{ $cand->current_employer ?? 'N/A' }}</div>
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                @foreach($cand->skills->take(4) as $sk)
                                    <span class="inline-block px-1.5 py-0.5 mr-1 mb-1 rounded bg-slate-100 dark:bg-slate-800 text-[10px] font-medium">{{ $sk->skill_name }}</span>
                                @endforeach
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="font-semibold">{{ $cand->total_experience }} Yrs</div>
                                <div class="text-[11px] text-slate-500">{{ $cand->notice_period_days }}d notice</div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div>{{ $cand->current_ctc ? number_format((float)$cand->current_ctc) : '-' }}</div>
                                <div class="text-[11px] text-emerald-600 font-semibold">→ {{ $cand->expected_ctc ? number_format((float)$cand->expected_ctc) : '-' }} {{ $cand->currency }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300 text-[10px] font-semibold uppercase">{{ str_replace('_', ' ', $cand->current_stage) }}</span>
                                <div class="text-[10px] text-slate-400 mt-1">{{ $cand->assignedRecruiter?->name ?? 'Unassigned' }}</div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <button type="button" @click="openCandidatePreview({{ $cand->id }})" class="text-[#1E40AF] font-semibold hover:underline">Preview</button>
                                    @if($cand->primaryResume)
                                        <a href="{{ route('documents.download', $cand->primaryResume->storage_record_id) }}" class="text-slate-600 hover:underline">CV</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">No candidates found matching your criteria.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-800">
            {{ $candidates->links() }}
        </div>
    </div>

    <!-- Bulk CSV Import Modal -->
    <div x-show="showImportModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4">
        <div @click.away="showImportModal = false" class="w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 text-xs">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Bulk Import Candidates from CSV</h3>
                <button type="button" @click="showImportModal = false" class="text-slate-400">✕</button>
            </div>
            <p class="mt-3 text-slate-500">Existing candidate profiles are never overwritten silently. Duplicate phone numbers or email addresses are automatically detected and reported.</p>
            <div class="mt-3">
                <a href="{{ route('candidates.import-template') }}" class="text-[#1E40AF] font-semibold hover:underline">⬇ Download Official CSV Import Template</a>
            </div>
            <form method="POST" action="{{ route('candidates.import') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="block font-semibold mb-1">Select CSV File *</label>
                    <input type="file" name="import_file" accept=".csv,.txt" required class="w-full">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="showImportModal = false" class="px-3.5 py-2 rounded-lg border border-slate-300">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">Run Validated Batch Import</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Candidate Modal -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 overflow-y-auto">
        <div @click.away="showCreateModal = false" class="w-full max-w-4xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Create Candidate Master Profile</h3>
                <button type="button" @click="showCreateModal = false" class="text-xs text-slate-400">✕</button>
            </div>
            <form method="POST" action="{{ route('candidates.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4 text-xs">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <div>
                        <label class="block font-semibold mb-1">Full Name *</label>
                        <input type="text" name="full_name" value="{{ old('full_name') }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Mobile Number *</label>
                        <input type="text" name="mobile_number" value="{{ old('mobile_number') }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Alternate Number</label>
                        <input type="text" name="alternate_number" value="{{ old('alternate_number') }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Email Address</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Current Location</label>
                        <input type="text" name="current_location" value="{{ old('current_location', 'Mumbai') }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Preferred Location</label>
                        <input type="text" name="preferred_location" value="{{ old('preferred_location', 'Mumbai') }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Current Employer</label>
                        <input type="text" name="current_employer" value="{{ old('current_employer') }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Current Designation</label>
                        <input type="text" name="current_designation" value="{{ old('current_designation') }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Qualifications</label>
                        <input type="text" name="qualifications" value="{{ old('qualifications') }}" placeholder="B.Tech / MBA / MCA" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Total Experience (Yrs)</label>
                        <input type="number" step="0.1" name="total_experience" value="{{ old('total_experience', 4) }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Relevant Experience (Yrs)</label>
                        <input type="number" step="0.1" name="relevant_experience" value="{{ old('relevant_experience', 3.5) }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Notice Period (Days)</label>
                        <input type="number" name="notice_period_days" value="{{ old('notice_period_days', 30) }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Current Annual CTC</label>
                        <input type="number" name="current_ctc" value="{{ old('current_ctc') }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Expected Annual CTC</label>
                        <input type="number" name="expected_ctc" value="{{ old('expected_ctc') }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Candidate Source</label>
                        <select name="source" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            @foreach((array)$sources as $src)
                                <option value="{{ $src }}">{{ $src }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block font-semibold mb-1">Skills (Comma separated)</label>
                        <input type="text" name="skills_csv" value="{{ old('skills_csv') }}" placeholder="PHP, Laravel, React, MySQL, AWS" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Associate with Active Job</label>
                        <select name="job_requirement_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            <option value="">-- Master Database Only --</option>
                            @foreach($activeJobs as $aj)
                                <option value="{{ $aj->id }}">{{ $aj->job_code }} — {{ $aj->title }} ({{ $aj->client?->company_name }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block font-semibold mb-1">Upload Resume to Private Vault (PDF, DOC, DOCX)</label>
                        <input type="file" name="resume_file" class="w-full">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">DPDP Data Consent Status</label>
                        <select name="consent_status" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            <option value="granted">Explicit Consent Granted</option>
                            <option value="pending">Consent Pending</option>
                        </select>
                    </div>
                </div>

                @if(session('duplicate_warning'))
                    <div class="p-3 rounded-lg bg-amber-50 border border-amber-300 text-amber-900">
                        <label class="inline-flex items-center gap-2 font-semibold">
                            <input type="checkbox" name="confirm_duplicate_override" value="1">
                            <span>Confirm saving as a separate candidate record despite potential duplicate warning</span>
                        </label>
                    </div>
                @endif

                <div class="flex justify-end pt-3 border-t border-slate-200 dark:border-slate-800">
                    <button type="submit" class="px-5 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">Save Candidate Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
