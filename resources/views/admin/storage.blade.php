@extends('layouts.app')
@section('title', 'Storage Management & Quota Monitor')
@section('page_title', 'Hostinger 20 GB NVMe Storage Management & Private Vault Ledger')
@section('page_subtitle', 'Real-time storage breakdown, SHA-256 deduplication, and 70% / 85% / 95% threshold protection')

@section('content')
@php
    $fmt = fn(int $b) => $b >= 1073741824 ? number_format($b / 1073741824, 2).' GB' : ($b >= 1048576 ? number_format($b / 1048576, 2).' MB' : number_format($b / 1024, 1).' KB');
@endphp

<!-- Quota Progress & Threshold Banner -->
<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 mb-6 text-xs">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-3">
        <div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Hostinger Available CRM Quota Utilisation ({{ $metrics['utilisation_percent'] }}%)</h2>
            <p class="text-slate-500">Used: <strong>{{ $fmt($metrics['total_used_bytes']) }}</strong> of <strong>{{ $fmt($metrics['quota_bytes']) }}</strong> • Remaining Capacity: <strong class="text-emerald-600">{{ $fmt($metrics['remaining_bytes']) }}</strong></p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 rounded text-[10px] font-bold uppercase {{ $metrics['utilisation_percent'] >= 70 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600' }}">70% Warn</span>
            <span class="px-2.5 py-1 rounded text-[10px] font-bold uppercase {{ $metrics['utilisation_percent'] >= 85 ? 'bg-orange-100 text-orange-800' : 'bg-slate-100 text-slate-600' }}">85% High</span>
            <span class="px-2.5 py-1 rounded text-[10px] font-bold uppercase {{ $metrics['utilisation_percent'] >= 95 ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-600' }}">95% Critical</span>
        </div>
    </div>
    <div class="w-full h-3 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
        <div class="h-full {{ $metrics['utilisation_percent'] >= 85 ? 'bg-rose-600' : ($metrics['utilisation_percent'] >= 70 ? 'bg-amber-500' : 'bg-emerald-600') }}"
             style="width: {{ max(1, min(100, $metrics['utilisation_percent'])) }}%"></div>
    </div>
</div>

<!-- Category Breakdown Cards -->
<div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 mb-6 text-xs tabular-nums">
    <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="text-[10px] text-slate-400 uppercase">Total CRM Used</div>
        <div class="text-sm font-bold mt-1">{{ $fmt($metrics['total_used_bytes']) }}</div>
    </div>
    <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="text-[10px] text-slate-400 uppercase">Resume Vault</div>
        <div class="text-sm font-bold text-[#1E40AF] mt-1">{{ $fmt($metrics['resume_bytes']) }}</div>
    </div>
    <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="text-[10px] text-slate-400 uppercase">JD Storage</div>
        <div class="text-sm font-bold mt-1">{{ $fmt($metrics['jd_bytes']) }}</div>
    </div>
    <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="text-[10px] text-slate-400 uppercase">Offer Letters</div>
        <div class="text-sm font-bold mt-1">{{ $fmt($metrics['offer_bytes']) }}</div>
    </div>
    <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="text-[10px] text-slate-400 uppercase">Other Docs</div>
        <div class="text-sm font-bold mt-1">{{ $fmt($metrics['other_bytes'] + $metrics['agreement_bytes'] + $metrics['joining_bytes']) }}</div>
    </div>
    <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="text-[10px] text-slate-400 uppercase">Database Usage</div>
        <div class="text-sm font-bold mt-1">{{ $fmt($metrics['database_bytes']) }}</div>
    </div>
    <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="text-[10px] text-slate-400 uppercase">Stored Files</div>
        <div class="text-sm font-bold mt-1">{{ $metrics['total_files_count'] }}</div>
    </div>
    <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="text-[10px] text-slate-400 uppercase">30-Day Growth</div>
        <div class="text-sm font-bold text-emerald-600 mt-1">+{{ $fmt($metrics['monthly_growth_bytes']) }}</div>
    </div>
</div>

<!-- Private Files Ledger -->
<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
    <div class="p-4 border-b border-slate-200 dark:border-slate-800">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Private File Storage Vault (SHA-256 Deduplicated)</h3>
    </div>
    <table class="w-full text-left text-xs">
        <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
            <tr>
                <th class="px-4 py-3">Category</th>
                <th class="px-4 py-3">Original Filename</th>
                <th class="px-4 py-3">MIME & Size</th>
                <th class="px-4 py-3">SHA-256 Checksum</th>
                <th class="px-4 py-3">Uploaded By</th>
                <th class="px-4 py-3 text-right">Download</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
            @forelse($recentFiles as $file)
                <tr>
                    <td class="px-4 py-3 uppercase font-bold text-[10px] text-[#D3003B]">{{ $file->category }}</td>
                    <td class="px-4 py-3 font-semibold">{{ $file->original_name }}</td>
                    <td class="px-4 py-3">{{ $file->mime_type }} • {{ $file->formattedSize() }}</td>
                    <td class="px-4 py-3 font-mono text-[11px] text-slate-500">{{ substr($file->sha256_checksum, 0, 20) }}...</td>
                    <td class="px-4 py-3">{{ $file->uploader?->name ?? 'System' }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('documents.download', $file) }}" class="px-2.5 py-1 rounded bg-[#0B1528] text-white font-semibold text-[11px]">Download</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No private documents uploaded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-4 border-t border-slate-100 dark:border-slate-800">{{ $recentFiles->links() }}</div>
</div>
@endsection
