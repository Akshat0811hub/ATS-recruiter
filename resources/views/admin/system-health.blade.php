@extends('layouts.app')
@section('title', 'System health')
@section('page_title', 'System health')
@section('page_subtitle', 'Check database, email, scheduled tasks and backup status.')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 text-xs">
    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="text-[10px] text-slate-400 uppercase">Application & Runtime</div>
        <div class="text-sm font-bold text-slate-900 dark:text-white mt-1">OrbiTouch ATS {{ $health['app_version'] }}</div>
        <div class="text-[11px] text-slate-500 mt-1">PHP {{ $health['php_version'] }} • Laravel {{ $health['laravel_version'] }}</div>
    </div>

    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="text-[10px] text-slate-400 uppercase">Database Connectivity</div>
        <div class="text-sm font-bold mt-1 {{ $health['db_connected'] ? 'text-emerald-600' : 'text-rose-600' }}">
            {{ $health['db_connected'] ? '● Connected & Healthy' : '● Disconnected' }}
        </div>
        <div class="text-[11px] text-slate-500 mt-1">Driver: {{ strtoupper($health['db_driver']) }} • TZ: {{ $health['timezone'] }}</div>
    </div>

    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="text-[10px] text-slate-400 uppercase">Storage & Quota Health</div>
        <div class="text-sm font-bold text-slate-900 dark:text-white mt-1">{{ $health['storage_metrics']['utilisation_percent'] }}% Utilised</div>
        <div class="text-[11px] text-slate-500 mt-1">Status: {{ strtoupper($health['storage_metrics']['alert_level']) }} • {{ $health['storage_metrics']['total_files_count'] }} Private Files</div>
    </div>

    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div class="text-[10px] text-slate-400 uppercase">Queue, SMTP & Backups</div>
        <div class="text-sm font-bold text-slate-900 dark:text-white mt-1">Mailer: {{ strtoupper($health['mail_mailer']) }}</div>
        <div class="text-[11px] text-slate-500 mt-1">Failed Jobs: {{ $health['failed_jobs_count'] }} • Backup Failures: {{ $health['failed_backups_count'] }}</div>
    </div>
</div>

<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 text-xs space-y-4">
    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Hostinger Premium Web Hosting Operational Checklist</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="p-3.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800">
            <div class="font-bold">Last Verified Encrypted Backup</div>
            <div class="text-slate-500 mt-1">
                @if($health['last_backup'])
                    {{ $health['last_backup']->backup_code }} on {{ $health['last_backup']->completed_at?->format('d M Y, H:i:s') }} (SHA-256 Verified)
                @else
                    No backup recorded yet.
                @endif
            </div>
        </div>
        <div class="p-3.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800">
            <div class="font-bold">External Off-Server Backup Readiness</div>
            <div class="mt-1 {{ $health['external_backup_ready'] ? 'text-emerald-600 font-semibold' : 'text-amber-600 font-semibold' }}">
                {{ $health['external_backup_ready'] ? 'Configured' : 'Pending external destination configuration in .env (ATS_BACKUP_EXTERNAL_DISK)' }}
            </div>
        </div>
    </div>
</div>
@endsection
