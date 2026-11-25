@extends('layouts.app')
@section('title', 'Email templates')
@section('page_title', 'Email templates')
@section('page_subtitle', 'Edit the messages used for recruitment updates.')

@section('content')
<div class="space-y-4 text-xs">
    @foreach($templates as $tpl)
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <span class="font-mono text-[#D3003B] font-bold">{{ $tpl->slug }}</span>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $tpl->name }}</h3>
                </div>
                <span class="text-[11px] text-slate-400">Variables: {{ implode(', ', $tpl->available_variables ?? []) }}</span>
            </div>
            <form method="POST" action="{{ route('admin.email-templates.update', $tpl) }}" class="space-y-3">
                @csrf
                @method('PUT')
                <div>
                    <label class="block font-semibold mb-1">Subject Line</label>
                    <input type="text" name="subject" value="{{ $tpl->subject }}" required class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                </div>
                <div>
                    <label class="block font-semibold mb-1">HTML Body</label>
                    <textarea name="body_html" rows="3" required class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent font-mono">{{ $tpl->body_html }}</textarea>
                </div>
                <div class="flex items-center justify-between">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" {{ $tpl->is_active ? 'checked' : '' }}>
                        <span>Template Active</span>
                    </label>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-[#0B1528] text-white font-semibold">Save Template</button>
                </div>
            </form>
        </div>
    @endforeach
</div>
@endsection
