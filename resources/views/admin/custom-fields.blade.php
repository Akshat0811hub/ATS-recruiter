@extends('layouts.app')
@section('title', 'Custom fields')
@section('page_title', 'Custom fields')
@section('page_subtitle', 'Manage additional fields for your recruitment records.')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 text-xs">
    <div class="lg:col-span-5">
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Create Custom Field</h2>
            <form method="POST" action="{{ route('admin.custom-fields.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block font-semibold mb-1">Entity Type *</label>
                    <select name="entity_type" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                        <option value="candidate">Candidate Profile</option>
                        <option value="job_requirement">Job Requirement</option>
                        <option value="client">Client Organisation</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold mb-1">Field Label *</label>
                    <input type="text" name="field_label" required placeholder="e.g. Passport / Visa Validity" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Data Type *</label>
                    <select name="field_type" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                        <option value="text">Text</option>
                        <option value="number">Number</option>
                        <option value="date">Date</option>
                        <option value="select">Dropdown Select</option>
                        <option value="boolean">Yes / No</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold mb-1">Dropdown Options (Comma separated, if Select)</label>
                    <input type="text" name="options_csv" placeholder="Option A, Option B" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                </div>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="is_required" value="1">
                    <span>Mandatory Field</span>
                </label>
                <button type="submit" class="w-full py-2 rounded-lg bg-[#D3003B] text-white font-semibold">Save Custom Field</button>
            </form>
        </div>
    </div>

    <div class="lg:col-span-7">
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                    <tr>
                        <th class="px-4 py-3">Entity</th>
                        <th class="px-4 py-3">Field Key</th>
                        <th class="px-4 py-3">Label</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Required</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($customFields as $cf)
                        <tr>
                            <td class="px-4 py-3 uppercase font-semibold">{{ str_replace('_', ' ', $cf->entity_type) }}</td>
                            <td class="px-4 py-3 font-mono text-[#D3003B]">{{ $cf->field_key }}</td>
                            <td class="px-4 py-3 font-bold">{{ $cf->field_label }}</td>
                            <td class="px-4 py-3 uppercase text-[10px]">{{ $cf->field_type }}</td>
                            <td class="px-4 py-3">{{ $cf->is_required ? 'Yes' : 'Optional' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
