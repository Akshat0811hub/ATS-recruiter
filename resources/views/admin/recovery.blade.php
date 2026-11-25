@extends('layouts.app')
@section('title', 'Recover deleted records')
@section('page_title', 'Recover deleted records')
@section('page_subtitle', 'Restore authorised records from the recovery list.')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 text-xs">
    <!-- Deleted Candidates -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Soft-Deleted Candidates ({{ $deletedCandidates->count() }})</h2>
        <div class="space-y-2.5">
            @forelse($deletedCandidates as $dc)
                <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <div class="font-bold text-[#D3003B]">{{ $dc->candidate_code }} — {{ $dc->full_name }}</div>
                        <div class="text-[10px] text-slate-400">Deleted: {{ $dc->deleted_at?->format('d M Y H:i') }}</div>
                    </div>
                    <form method="POST" action="{{ route('admin.recovery.restore') }}">
                        @csrf
                        <input type="hidden" name="type" value="candidate">
                        <input type="hidden" name="id" value="{{ $dc->id }}">
                        <button type="submit" class="px-2.5 py-1 rounded bg-emerald-600 text-white font-semibold text-[10px]">Restore</button>
                    </form>
                </div>
            @empty
                <div class="py-4 text-slate-400">No soft-deleted candidates.</div>
            @endforelse
        </div>
    </div>

    <!-- Deleted Jobs -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Soft-Deleted Jobs ({{ $deletedJobs->count() }})</h2>
        <div class="space-y-2.5">
            @forelse($deletedJobs as $dj)
                <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <div class="font-bold text-[#D3003B]">{{ $dj->job_code }} — {{ $dj->title }}</div>
                        <div class="text-[10px] text-slate-400">Deleted: {{ $dj->deleted_at?->format('d M Y H:i') }}</div>
                    </div>
                    <form method="POST" action="{{ route('admin.recovery.restore') }}">
                        @csrf
                        <input type="hidden" name="type" value="job">
                        <input type="hidden" name="id" value="{{ $dj->id }}">
                        <button type="submit" class="px-2.5 py-1 rounded bg-emerald-600 text-white font-semibold text-[10px]">Restore</button>
                    </form>
                </div>
            @empty
                <div class="py-4 text-slate-400">No soft-deleted job requirements.</div>
            @endforelse
        </div>
    </div>

    <!-- Deleted Clients -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Soft-Deleted Clients ({{ $deletedClients->count() }})</h2>
        <div class="space-y-2.5">
            @forelse($deletedClients as $dcl)
                <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <div class="font-bold text-[#D3003B]">{{ $dcl->client_code }} — {{ $dcl->company_name }}</div>
                        <div class="text-[10px] text-slate-400">Deleted: {{ $dcl->deleted_at?->format('d M Y H:i') }}</div>
                    </div>
                    <form method="POST" action="{{ route('admin.recovery.restore') }}">
                        @csrf
                        <input type="hidden" name="type" value="client">
                        <input type="hidden" name="id" value="{{ $dcl->id }}">
                        <button type="submit" class="px-2.5 py-1 rounded bg-emerald-600 text-white font-semibold text-[10px]">Restore</button>
                    </form>
                </div>
            @empty
                <div class="py-4 text-slate-400">No soft-deleted clients.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
