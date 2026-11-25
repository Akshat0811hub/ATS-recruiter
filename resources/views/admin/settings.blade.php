@extends('layouts.app')
@section('title', 'Settings')
@section('page_title', 'Settings')
@section('page_subtitle', 'Manage company details and recruitment preferences.')

@section('content')
<div class="max-w-4xl bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 text-xs">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-4">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block font-semibold mb-1">Company Brand Name *</label>
                <input type="text" name="company_name" value="{{ $organisation?->name ?? 'OrbiTouch HR & Recruitment Consultants' }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
            </div>
            <div>
                <label class="block font-semibold mb-1">Legal Entity Name *</label>
                <input type="text" name="legal_name" value="{{ $organisation?->legal_name ?? 'Orbitouch Outsourcing Private Limited' }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
            </div>
            <div>
                <label class="block font-semibold mb-1">Default Timezone *</label>
                <input type="text" name="default_timezone" value="{{ $organisation?->default_timezone ?? 'Asia/Kolkata' }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
            </div>
            <div>
                <label class="block font-semibold mb-1">Default Currency *</label>
                <input type="text" name="default_currency" value="{{ $organisation?->default_currency ?? 'INR' }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
            </div>
            <div>
                <label class="block font-semibold mb-1">Work Day Start (Mon–Sat IST) *</label>
                <input type="text" name="work_start_time" value="{{ $organisation?->work_start_time ?? '10:00' }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
            </div>
            <div>
                <label class="block font-semibold mb-1">Work Day End (Mon–Sat IST) *</label>
                <input type="text" name="work_end_time" value="{{ $organisation?->work_end_time ?? '18:30' }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
            </div>
            <div>
                <label class="block font-semibold mb-1">Standard Replacement Period (Days) *</label>
                <input type="number" name="default_replacement_days" value="{{ $organisation?->default_replacement_days ?? 90 }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
            </div>
            <div>
                <label class="block font-semibold mb-1">DPDP Candidate Data Retention (Months) *</label>
                <input type="number" name="dpdp_retention_months" value="{{ \App\Models\SystemSetting::getValue('dpdp_retention_months', 36) }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
            </div>
        </div>

        <div>
            <label class="block font-semibold mb-1">Configurable Candidate Sources (Comma separated)</label>
            <input type="text" name="candidate_sources"
                   value="{{ implode(', ', (array)\App\Models\SystemSetting::getValue('candidate_sources', ['Direct Sourcing', 'Naukri', 'LinkedIn Recruiter', 'Employee Referral'])) }}"
                   class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
        </div>

        <div>
            <label class="block font-semibold mb-1">Configurable Rejection Reasons (Comma separated)</label>
            <input type="text" name="rejection_reasons"
                   value="{{ implode(', ', (array)\App\Models\SystemSetting::getValue('rejection_reasons', ['Technical Skills Gap', 'Salary Expectation Mismatch', 'Notice Period Too Long'])) }}"
                   class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
        </div>

        <div class="flex justify-end pt-3 border-t border-slate-200 dark:border-slate-800">
            <button type="submit" class="px-5 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">Save Business Settings</button>
        </div>
    </form>
</div>
@endsection
