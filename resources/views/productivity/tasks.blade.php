@extends('layouts.app')
@section('title', 'Tasks & follow-ups')
@section('page_title', 'Tasks & follow-ups')
@section('page_subtitle', 'See what is due, plan your next action and mark finished work complete.')

@section('content')
<div x-data="{ showCreateTask: {{ request()->boolean('create') || $errors->any() ? 'true' : 'false' }} }">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 text-xs">
        <div class="flex flex-wrap items-center gap-2">
            @foreach(['today' => "Today's Tasks", 'pending' => 'Pending', 'upcoming' => 'Upcoming', 'overdue' => 'Overdue', 'completed' => 'Completed', 'all' => 'All Tasks'] as $fKey => $fLabel)
                <a href="{{ route('tasks.index', ['filter' => $fKey]) }}"
                   class="px-3.5 py-1.5 rounded-lg font-semibold {{ $filter === $fKey ? 'bg-[#0B1528] text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300' }}">
                    {{ $fLabel }}
                </a>
            @endforeach
        </div>
        <button type="button" @click="showCreateTask = true" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">+ Create Follow-up Task</button>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                <tr>
                    <th class="px-4 py-3">Task & Type</th>
                    <th class="px-4 py-3">Candidate / Job / Client</th>
                    <th class="px-4 py-3">Assigned To</th>
                    <th class="px-4 py-3">Due Date & Priority</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
                @forelse($tasks as $task)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3">
                            <div class="font-bold text-slate-900 dark:text-white">{{ $task->title }}</div>
                            <div class="text-[11px] text-slate-500">{{ \App\Models\Task::TYPES[$task->task_type] ?? $task->task_type }} @if($task->is_recurring) • ↻ Recurring ({{ $task->recurrence_pattern }}) @endif</div>
                        </td>
                        <td class="px-4 py-3">
                            @if($task->candidate)
                                <a href="{{ route('candidates.show', $task->candidate) }}" class="font-semibold text-[#1E40AF] hover:underline">{{ $task->candidate->full_name }}</a>
                            @endif
                            @if($task->jobRequirement)
                                <div class="text-[11px] text-slate-500">{{ $task->jobRequirement->job_code }} — {{ $task->jobRequirement->title }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $task->assignee?->name }}</td>
                        <td class="px-4 py-3">
                            <div class="font-semibold">{{ $task->due_date?->format('d M Y') }} {{ $task->due_time }}</div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $task->priority === 'critical' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800' }}">{{ $task->priority }}</span>
                        </td>
                        <td class="px-4 py-3 uppercase text-[10px] font-bold {{ $task->status === 'completed' ? 'text-emerald-600' : 'text-amber-600' }}">{{ $task->status }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($task->status !== 'completed')
                                <form method="POST" action="{{ route('tasks.complete', $task) }}">
                                    @csrf
                                    <button type="submit" class="px-3 py-1 rounded bg-emerald-600 text-white font-semibold text-[11px]">Mark Complete</button>
                                </form>
                            @else
                                <span class="text-slate-400">{{ $task->completed_at?->format('d M H:i') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No tasks found for this filter.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4 border-t border-slate-100 dark:border-slate-800">{{ $tasks->links() }}</div>
    </div>

    <!-- Create Task Modal -->
    <div x-show="showCreateTask" x-cloak role="dialog" aria-modal="true" aria-label="productivity form" @keydown.escape.window="showCreateTask = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4">
        <div @click.away="showCreateTask = false" class="w-full max-w-xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 text-xs">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white pb-3 border-b border-slate-200">Create Recruitment Follow-up Task</h3>
            <form method="POST" action="{{ route('tasks.store') }}" class="mt-4 space-y-3" x-data="{ recurring: false }">
                @csrf
                @if($errors->any())
                    <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-rose-800">
                        <ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <div>
                    <label class="block font-semibold mb-1">Task Title *</label>
                    <input type="text" name="title" value="{{ old('title') }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold mb-1">Task Type *</label>
                        <select name="task_type" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            @foreach(\App\Models\Task::TYPES as $tk => $tl)
                                <option value="{{ $tk }}" @selected(old('task_type') === $tk)>{{ $tl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Assigned User *</label>
                        <select name="assigned_to" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" {{ old('assigned_to', auth()->id()) == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Due Date *</label>
                        <input type="date" name="due_date" value="{{ old('due_date', now()->toDateString()) }}" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Priority</label>
                        <select name="priority" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900">
                            <option value="critical">Critical</option>
                            <option value="high" selected>High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 p-3 border border-slate-200">
                    <label class="inline-flex items-center gap-2 font-semibold text-slate-700">
                        <input type="checkbox" name="is_recurring" value="1" x-model="recurring">
                        <span>Repeat automatically after completion</span>
                    </label>
                    <select x-show="recurring" name="recurrence_pattern" class="px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white">
                        <option value="daily">Daily</option>
                        <option value="weekly" selected>Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-200">
                    <button type="button" @click="showCreateTask = false" class="px-3.5 py-2 rounded-lg border border-slate-300">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-[#D3003B] text-white font-semibold">Save Task</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
