@extends('layouts.app')
@section('title', 'Encrypted Backups & Data Restoration')
@section('page_title', 'Encrypted Daily Backups & Integrity-Verified Restoration')
@section('page_subtitle', 'AES-256 encrypted database and private document snapshots with SHA-256 checksum verification')

@section('content')
@if(!$externalConfigured)
    <div class="mb-5 p-4 rounded-xl bg-amber-50 dark:bg-amber-950/50 border border-amber-300 dark:border-amber-700 text-amber-900 dark:text-amber-200 text-xs">
        <div class="font-bold">⚠️ External Off-Server Backup Destination Pending Configuration</div>
        <p class="mt-0.5">Encrypted daily backups are active in the local vault, but an external destination (<code>ATS_BACKUP_EXTERNAL_DISK</code> in <code>.env</code>) has not been configured yet. Configure an approved external S3/SFTP vault before final production cutover so backups are stored off the 20 GB Hostinger account.</p>
    </div>
@endif

<div class="mb-5 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
    <div>
        <div class="font-bold text-slate-900 dark:text-white">Automated Daily Encrypted Backup Schedule: 02:00 AM IST</div>
        <div class="text-slate-500">Covers all 44 relational tables + private resume, JD, agreement, and offer document vault manifest.</div>
    </div>
    <form method="POST" action="{{ route('admin.backups.run') }}" class="flex items-center gap-2">
        @csrf
        <select name="backup_type" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
            <option value="full">Full Encrypted Snapshot</option>
            <option value="incremental">Incremental Document Backup</option>
        </select>
        <button type="submit" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">
            ⚡ Trigger Encrypted Backup Now
        </button>
    </form>
</div>

<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
    <table class="w-full text-left text-xs">
        <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
            <tr>
                <th class="px-4 py-3">Backup Code</th>
                <th class="px-4 py-3">Type & Destination</th>
                <th class="px-4 py-3">Encrypted Size</th>
                <th class="px-4 py-3">SHA-256 Integrity Checksum</th>
                <th class="px-4 py-3">Status & Timestamp</th>
                <th class="px-4 py-3 text-right">Verified Restoration</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
            @forelse($backups as $bkp)
                <tr>
                    <td class="px-4 py-3 font-mono font-bold text-[#D3003B]">{{ $bkp->backup_code }}</td>
                    <td class="px-4 py-3">
                        <span class="uppercase font-semibold">{{ $bkp->backup_type }}</span>
                        <div class="text-[10px] text-slate-400">{{ $bkp->destination }} • AES-256 Encrypted</div>
                    </td>
                    <td class="px-4 py-3">{{ number_format($bkp->size_bytes / 1024, 1) }} KB</td>
                    <td class="px-4 py-3 font-mono text-[11px] text-slate-500">{{ substr($bkp->sha256_checksum, 0, 24) }}...</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800">{{ $bkp->status }}</span>
                        <div class="text-[10px] text-slate-400 mt-1">{{ $bkp->completed_at?->format('d M Y, H:i:s') }}</div>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('admin.backups.restore', $bkp) }}">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-[#0B1528] text-white font-semibold text-[11px]">
                                Verify SHA-256 & Restore
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No backups generated yet. Click "Trigger Encrypted Backup Now" above.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
