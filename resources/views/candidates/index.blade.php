@extends('layouts.app')
@section('title', 'Candidates')
@section('page_title', 'Candidates')
@section('page_subtitle', 'Find candidates, keep profiles up to date and add them to the right jobs.')

@section('content')
<div x-data="{
    showCreateModal: {{ request()->boolean('create') || session('duplicate_warning') || ($errors->any() && old('_form') !== 'candidate_import') ? 'true' : 'false' }},
    showImportModal: {{ $errors->any() && old('_form') === 'candidate_import' ? 'true' : 'false' }}
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
            @if(request()->anyFilled(['search', 'location', 'recruiter_id', 'max_notice_days']))
                <a href="{{ route('candidates.index') }}" class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-slate-600 font-semibold hover:bg-slate-50">Reset</a>
            @endif
        </form>

        <div class="flex items-center gap-2">
            @if(auth()->user()->hasRole(['super_admin', 'manager', 'team_leader']))
                <button type="button" @click="showImportModal = true" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold hover:bg-slate-100">
                    Import CSV
                </button>
            @endif
            @if(auth()->user()->hasRole(['super_admin', 'manager']))
                <a href="{{ route('candidates.export', request()->query()) }}" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold hover:bg-slate-100">
                    Export CSV
                </a>
            @endif
            <button type="button" @click="showCreateModal = true" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">
                + Add candidate
            </button>
        </div>
    </div>

    @if(session('import_errors'))<details open class="panel mb-5 p-4 text-sm"><summary class="font-semibold">Import details</summary><ul class="mt-3 list-inside list-disc text-slate-600">@foreach(session('import_errors') as $error)<li>{{ $error }}</li>@endforeach</ul></details>@endif
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
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="font-semibold">{{ $cand->total_experience ?? 0 }} Yrs</div>
                                <div class="text-[11px] text-slate-500">{{ $cand->notice_period_days !== null ? $cand->notice_period_days.'d notice' : 'Notice N/A' }}</div>
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
    <div x-show="showImportModal" x-cloak role="dialog" aria-modal="true" aria-label="Import candidates" @keydown.escape.window="showImportModal = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4">
        <div @click.away="showImportModal = false" class="w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 text-xs">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Import candidates</h3>
                <button type="button" @click="showImportModal = false" class="text-slate-400">✕</button>
            </div>
            <p class="mt-3 text-slate-500">Existing candidate profiles are never overwritten silently. Duplicate phone numbers or email addresses are automatically detected and reported.</p>
            <div class="mt-3">
                <a href="{{ route('candidates.import-template') }}" class="text-[#1E40AF] font-semibold hover:underline">⬇ Download CSV template</a>
            </div>
            @if($errors->any() && old('_form') === 'candidate_import')
                <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-rose-800">
                    <div class="font-semibold">Unable to import CSV:</div>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <form method="POST" action="{{ route('candidates.import') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="block font-semibold mb-1">Select CSV File *</label>
                    <input type="hidden" name="_form" value="candidate_import"><input type="file" name="import_file" accept=".csv,.txt" required class="w-full">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="showImportModal = false" class="px-3.5 py-2 rounded-lg border border-slate-300">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">Import candidates</button>
                </div>
            </form>
        </div>
    </div>


    <div x-show="showCreateModal" x-cloak role="dialog" aria-modal="true" aria-labelledby="add-candidate-title" @click.self="showCreateModal = false" @keydown.escape.window="showCreateModal = false" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/30 p-4 pt-[5vh]">
        <section class="w-full max-w-2xl max-h-[90dvh] overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4"><div><h2 id="add-candidate-title" class="text-lg font-semibold">Add candidate</h2><p class="mt-1 text-sm text-slate-500">Start with the essentials. Add more details when you have them.</p></div><button type="button" @click="showCreateModal = false" class="icon-button" aria-label="Close candidate form"><x-icon name="close" /></button></div>
            <form method="POST" action="{{ route('candidates.store') }}" enctype="multipart/form-data" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="_form" value="candidate_create">
                @if(session('duplicate_warning'))
                    <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">{{ session('duplicate_warning') }}</div>
                @endif
                @if($errors->any() && old('_form') !== 'candidate_import')
                    <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
                        <p class="font-semibold">Please correct the highlighted details:</p>
                        <ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <p class="text-sm text-slate-500">Fields marked * are required.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm font-medium">Full name *<input type="text" name="full_name" value="{{ old('full_name') }}" autocomplete="name" required class="field mt-1"></label>
                    <label class="text-sm font-medium">Mobile number *<input type="tel" name="mobile_number" value="{{ old('mobile_number') }}" autocomplete="tel" required class="field mt-1" placeholder="e.g. +91 98765 43210"></label>
                    <label class="text-sm font-medium">Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" class="field mt-1"></label>
                    <label class="text-sm font-medium">Current location<input type="text" name="current_location" value="{{ old('current_location') }}" class="field mt-1" placeholder="City"></label>
                    <label class="text-sm font-medium">Source<select name="source" class="field mt-1">@foreach((array) $sources as $source)<option value="{{ $source }}" @selected(old('source') === $source)>{{ $source }}</option>@endforeach</select></label>
                    <label class="text-sm font-medium">Add to a job<select name="job_requirement_id" class="field mt-1"><option value="">Choose later</option>@foreach($activeJobs as $job)<option value="{{ $job->id }}" @selected(old('job_requirement_id') == $job->id)>{{ $job->title }} · {{ $job->client?->company_name }}</option>@endforeach</select></label>
                </div>
                <label class="block text-sm font-medium">Resume <span class="font-normal text-slate-500">(optional)</span><input type="file" name="resume_file" accept=".pdf,.doc,.docx" class="mt-2 block w-full rounded-lg border border-dashed border-slate-300 p-3 text-sm"><span class="mt-1 block text-xs font-normal text-slate-500">PDF, DOC or DOCX. Up to 10 MB. @if($errors->any())Please select the file again before saving.@endif</span></label>
                <label class="block text-sm font-medium">Candidate consent<select name="consent_status" class="field mt-1"><option value="pending" @selected(old('consent_status', 'pending') === 'pending')>Consent pending</option><option value="granted" @selected(old('consent_status') === 'granted')>Candidate has given consent</option></select><span class="mt-1 block text-xs font-normal text-slate-500">Record consent only after the candidate has agreed.</span></label>
                <details class="rounded-lg border border-slate-200" @if($errors->any()) open @endif><summary class="p-4 text-sm font-semibold text-slate-700">Experience, salary & other details <span class="font-normal text-slate-500">(optional)</span></summary><div class="grid gap-4 border-t border-slate-200 p-4 sm:grid-cols-2">
                    @foreach([
                        ['alternate_number', 'Alternate phone', 'tel'], ['preferred_location', 'Preferred location', 'text'], ['current_employer', 'Current employer', 'text'], ['current_designation', 'Current role', 'text'], ['qualifications', 'Qualifications', 'text'], ['total_experience', 'Total experience (years)', 'number'], ['relevant_experience', 'Relevant experience (years)', 'number'], ['notice_period_days', 'Notice period (days)', 'number'], ['current_ctc', 'Current annual CTC (INR)', 'number'], ['expected_ctc', 'Expected annual CTC (INR)', 'number'],
                    ] as [$field, $label, $type])
                    <label class="text-sm font-medium">{{ $label }}<input type="{{ $type }}" name="{{ $field }}" value="{{ old($field) }}" @if($type === 'number') min="0" step="{{ str_contains($field, 'experience') ? '0.1' : '1' }}" @endif class="field mt-1"></label>
                    @endforeach
                    <label class="text-sm font-medium sm:col-span-2">Skills<input type="text" name="skills_csv" value="{{ old('skills_csv') }}" placeholder="e.g. PHP, Laravel, SQL" class="field mt-1"><span class="mt-1 block text-xs font-normal text-slate-500">Separate skills with commas.</span></label>
                </div></details>
                @if(session('duplicate_warning') && auth()->user()->hasPermission('candidates.merge'))<label class="flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900"><input type="checkbox" name="confirm_duplicate_override" value="1" class="mt-1">I reviewed the duplicate warning and this is a different person.</label>@endif
                <div class="flex justify-end gap-2 border-t border-slate-200 pt-5"><button type="button" @click="showCreateModal = false" class="btn-secondary">Cancel</button><button type="submit" class="btn-primary">Save candidate</button></div>
            </form>
        </section>
    </div>
</div>
@endsection
