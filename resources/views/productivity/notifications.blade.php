@extends('layouts.app')
@section('title', 'Notification Centre')
@section('page_title', 'In-App Notification Centre')
@section('page_subtitle', 'New requirements, recruiter assignments, client shortlists, interviews, offer updates, and system alerts')

@section('content')
<div class="mb-4 flex items-center justify-between text-xs">
    <span class="text-slate-500">Showing latest operational notifications</span>
    <form method="POST" action="{{ route('notifications.read-all') }}">
        @csrf
        <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-[#0B1528] text-white font-semibold">Mark All as Read</button>
    </form>
</div>

<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 divide-y divide-slate-100 dark:divide-slate-800 text-xs">
    @forelse($notifications as $notif)
        @php $data = is_array($notif->data) ? $notif->data : []; @endphp
        <div class="p-4 flex items-center justify-between gap-4 {{ $notif->read_at ? 'opacity-75' : 'bg-blue-50/40 dark:bg-blue-950/20' }}">
            <div>
                <div class="flex items-center gap-2">
                    @if(!$notif->read_at)<span class="w-2 h-2 rounded-full bg-[#D3003B]"></span>@endif
                    <span class="font-bold text-slate-900 dark:text-white">{{ $data['title'] ?? 'System Notification' }}</span>
                    <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-[10px] uppercase">{{ $notif->category }}</span>
                </div>
                <p class="text-slate-600 dark:text-slate-300 mt-1">{{ $data['message'] ?? '' }}</p>
                <div class="text-[10px] text-slate-400 mt-1 tabular-nums">{{ $notif->created_at?->format('d M Y, H:i') }}</div>
            </div>
            <form method="POST" action="{{ route('notifications.read', $notif) }}">
                @csrf
                <button type="submit" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold hover:bg-slate-100">
                    {{ $notif->action_url ? 'Open Record →' : 'Mark Read' }}
                </button>
            </form>
        </div>
    @empty
        <div class="p-8 text-center text-slate-400">No notifications in your inbox.</div>
    @endforelse
</div>
<div class="mt-4">{{ $notifications->links() }}</div>
@endsection
